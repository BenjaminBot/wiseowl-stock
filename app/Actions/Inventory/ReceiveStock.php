<?php

namespace App\Actions\Inventory;

use App\Models\IntakeSession;
use App\Models\ReceiptLine;
use App\Models\SalesChannel;
use App\Models\StockBalance;
use App\Models\StockBatch;
use App\Models\StockLedgerEntry;
use App\Models\TaxCategory;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ReceiveStock
{
    public function execute(User $actor, array $input): ReceiptLine
    {
        Gate::forUser($actor)->authorize('receive-stock');
        $data = Validator::make($input, [
            'idempotency_key' => ['required', 'uuid'],
            'intake_session_id' => ['required', 'integer', 'exists:intake_sessions,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'condition' => ['required', Rule::in(['new', 'almost_new', 'used'])],
            'cost_input' => ['required', 'regex:/^\d{1,10}(\.\d{1,4})?$/'],
            'cost_basis' => ['required', Rule::in(['exclusive', 'inclusive'])],
            'cost_mode' => ['required', Rule::in(['per_copy', 'line_total'])],
            'purchase_tax_category_id' => ['required', 'integer', 'exists:tax_categories,id'],
            'sales_tax_category_id' => ['required', 'integer', 'exists:tax_categories,id'],
            'selling_price' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'price_confirmed' => ['required', 'accepted'],
            'additional_quantity_confirmed' => ['sometimes', 'boolean'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sales_channel_id' => ['required', 'integer', 'distinct', 'exists:sales_channels,id'],
            'allocations.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ])->validate();

        // Stable canonical payload: semantic retries must not depend on numeric formatting or row order.
        foreach (['intake_session_id', 'product_id', 'quantity', 'purchase_tax_category_id', 'sales_tax_category_id'] as $key) {
            $data[$key] = (int) $data[$key];
        }
        $data['cost_input'] = (string) BigDecimal::of($data['cost_input'])->toScale(4);
        $data['selling_price'] = (string) BigDecimal::of($data['selling_price'])->toScale(2);
        $data['price_confirmed'] = true;
        $data['additional_quantity_confirmed'] = (bool) ($data['additional_quantity_confirmed'] ?? false);
        $data['allocations'] = array_map(fn (array $row) => [
            'sales_channel_id' => (int) $row['sales_channel_id'], 'quantity' => (int) $row['quantity'],
        ], $data['allocations']);
        usort($data['allocations'], fn ($a, $b) => $a['sales_channel_id'] <=> $b['sales_channel_id']);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $data, $hash) {
            // Serialises receipt saves, corrections, and future session closing for this delivery.
            $session = IntakeSession::query()->lockForUpdate()->findOrFail($data['intake_session_id']);
            $existing = ReceiptLine::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ($existing->received_by !== $actor->id || ! hash_equals($existing->request_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This operation key was already used for a different receipt.']);
                }

                return $existing;
            }
            if ($session->status !== 'open') {
                throw ValidationException::withMessages(['intake_session_id' => 'This delivery is closed.']);
            }
            if (! $data['additional_quantity_confirmed'] && ReceiptLine::where('intake_session_id', $session->id)->where('product_id', $data['product_id'])->exists()) {
                throw ValidationException::withMessages(['additional_quantity_confirmed' => 'This item is already in this delivery. Confirm that this is additional quantity; no existing line will be modified.']);
            }
            $allowed = collect($session->destinations)->pluck('sales_channel_id')->map(fn ($id) => (int) $id)->all();
            $unallocated = SalesChannel::query()->where('is_system', true)->where('type', 'unallocated')->sole();
            $allowed[] = $unallocated->id;
            foreach ($data['allocations'] as $allocation) {
                if (! in_array($allocation['sales_channel_id'], $allowed, true)) {
                    throw ValidationException::withMessages(['allocations' => 'Use the destinations configured for this delivery.']);
                }
            }
            if (array_sum(array_column($data['allocations'], 'quantity')) !== $data['quantity']) {
                throw ValidationException::withMessages(['allocations' => 'Destination quantities, including unallocated, must equal the received quantity.']);
            }

            $purchaseRate = TaxCategory::findOrFail($data['purchase_tax_category_id'])->rate;
            $salesRate = TaxCategory::findOrFail($data['sales_tax_category_id'])->rate;
            $cost = BigDecimal::of($data['cost_input']);
            $divisor = BigDecimal::one();
            if ($data['cost_mode'] === 'line_total') {
                $divisor = $divisor->multipliedBy($data['quantity']);
            }
            if ($data['cost_basis'] === 'inclusive') {
                $factor = BigDecimal::of($purchaseRate)->dividedBy(100, 6)->plus(1);
                $divisor = $divisor->multipliedBy($factor);
            }
            $unitNetCost = (string) $cost->dividedBy($divisor, 4, RoundingMode::HalfUp);
            $receipt = ReceiptLine::create([
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
                'intake_session_id' => $session->id, 'product_id' => $data['product_id'], 'received_by' => $actor->id,
                'quantity' => $data['quantity'], 'condition' => $data['condition'],
                'cost_input' => $data['cost_input'], 'cost_basis' => $data['cost_basis'], 'cost_mode' => $data['cost_mode'],
                'unit_net_cost' => $unitNetCost, 'purchase_tax_rate' => $purchaseRate,
                'selling_price' => $data['selling_price'], 'sales_tax_rate' => $salesRate, 'currency' => $session->currency,
            ]);
            $batch = StockBatch::create([
                'receipt_line_id' => $receipt->id, 'product_id' => $receipt->product_id, 'condition' => $receipt->condition,
                'unit_net_cost' => $unitNetCost, 'selling_price' => $receipt->selling_price,
                'sales_tax_rate' => $salesRate, 'currency' => $session->currency,
            ]);
            foreach ($data['allocations'] as $row) {
                if ($row['quantity'] === 0) {
                    continue;
                }
                $bucket = ['stock_batch_id' => $batch->id, 'location_id' => $session->receiving_location_id, 'sales_channel_id' => $row['sales_channel_id']];
                StockBalance::create($bucket + ['quantity' => $row['quantity'], 'reserved_quantity' => 0]);
                StockLedgerEntry::create($bucket + ['receipt_line_id' => $receipt->id, 'quantity_delta' => $row['quantity'], 'recorded_by' => $actor->id]);
            }

            return $receipt;
        }, attempts: 3);
    }
}
