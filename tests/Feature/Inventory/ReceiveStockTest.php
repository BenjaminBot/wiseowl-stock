<?php

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\ReceiveStock;
use App\Models\SalesChannel;
use App\Models\StockBalance;
use App\Models\StockBatch;
use App\Models\StockLedgerEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class ReceiveStockTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    public function test_receipt_posts_exact_stock_with_actual_location_and_audit(): void
    {
        $f = $this->inventoryFixture();
        $receipt = app(ReceiveStock::class)->execute($f['staff'], $f['input']);
        $this->assertSame(5, StockBalance::sum('quantity'));
        $this->assertSame(5, StockLedgerEntry::sum('quantity_delta'));
        $this->assertSame('8.99', $receipt->selling_price);
        $this->assertSame('4.5000', $receipt->unit_net_cost);
        $this->assertSame($f['staff']->id, $receipt->received_by);
        $this->assertSame([$f['location']->id], StockBalance::distinct()->pluck('location_id')->all());
        $this->assertSame('9780140328721', $f['product']->isbn);
    }

    public function test_retries_return_original_even_after_delivery_closes_or_tax_changes(): void
    {
        $f = $this->inventoryFixture();
        $first = app(ReceiveStock::class)->execute($f['staff'], $f['input']);
        $f['session']->update(['status' => 'closed']);
        $f['tax']->update(['rate' => '20']);
        $second = app(ReceiveStock::class)->execute($f['staff'], $f['input']);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('receipt_lines', 1);
        $this->assertSame('18.0000', $second->sales_tax_rate);
    }

    public function test_reused_key_with_changed_price_is_rejected(): void
    {
        $f = $this->inventoryFixture();
        app(ReceiveStock::class)->execute($f['staff'], $f['input']);
        $this->expectException(ValidationException::class);
        app(ReceiveStock::class)->execute($f['staff'], array_replace($f['input'], ['selling_price' => '9.99']));
    }

    public function test_duplicate_book_requires_explicit_additional_quantity_confirmation(): void
    {
        $f = $this->inventoryFixture();
        app(ReceiveStock::class)->execute($f['staff'], $f['input']);
        $this->expectException(ValidationException::class);
        app(ReceiveStock::class)->execute($f['staff'], array_replace($f['input'], ['idempotency_key' => (string) Str::uuid()]));
    }

    public function test_new_batch_does_not_reprice_previous_stock(): void
    {
        $f = $this->inventoryFixture();
        app(ReceiveStock::class)->execute($f['staff'], $f['input']);
        app(ReceiveStock::class)->execute($f['staff'], array_replace($f['input'], [
            'idempotency_key' => (string) Str::uuid(), 'additional_quantity_confirmed' => true, 'selling_price' => '5.99', 'condition' => 'used',
        ]));
        $this->assertSame(['8.99', '5.99'], StockBatch::orderBy('id')->pluck('selling_price')->all());
        $this->assertSame(10, StockBalance::sum('quantity'));
    }

    public function test_cost_including_tax_and_line_total_use_exact_decimal_rounding(): void
    {
        $f = $this->inventoryFixture();
        $receipt = app(ReceiveStock::class)->execute($f['staff'], array_replace($f['input'], [
            'cost_input' => '10', 'cost_basis' => 'inclusive', 'cost_mode' => 'line_total',
        ]));
        $this->assertSame('1.6949', $receipt->unit_net_cost);
        $this->assertSame('10.0000', $receipt->cost_input);
    }

    public function test_mismatched_split_and_unconfirmed_price_create_no_stock(): void
    {
        $f = $this->inventoryFixture();
        foreach ([['quantity' => 6], ['price_confirmed' => false]] as $change) {
            try {
                app(ReceiveStock::class)->execute($f['staff'], array_replace($f['input'], $change));
                $this->fail('Invalid receipt was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('receipt_lines', 0);
                $this->assertDatabaseCount('stock_balances', 0);
            }
        }
    }

    public function test_unknown_destination_and_closed_delivery_are_rejected(): void
    {
        $f = $this->inventoryFixture();
        $other = SalesChannel::create(['name' => 'Other shop', 'type' => 'shop']);
        $input = $f['input'];
        $input['allocations'][0]['sales_channel_id'] = $other->id;
        try {
            app(ReceiveStock::class)->execute($f['staff'], $input);
            $this->fail('Wrong destination accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('receipt_lines', 0);
        }
        $f['session']->update(['status' => 'closed']);
        $this->expectException(ValidationException::class);
        app(ReceiveStock::class)->execute($f['staff'], $f['input']);
    }

    public function test_non_staff_cannot_call_the_operation_directly(): void
    {
        $f = $this->inventoryFixture();
        $this->expectException(AuthorizationException::class);
        app(ReceiveStock::class)->execute(User::factory()->create(), $f['input']);
    }

    public function test_a_failure_in_ledger_posting_rolls_everything_back(): void
    {
        $f = $this->inventoryFixture();
        $event = 'eloquent.creating: '.StockLedgerEntry::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated posting failure'));
        try {
            app(ReceiveStock::class)->execute($f['staff'], $f['input']);
            $this->fail('Expected posting failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated posting failure', $e->getMessage());
        } finally {
            Event::forget($event);
        }
        foreach (['receipt_lines', 'stock_batches', 'stock_balances', 'stock_ledger_entries'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_posted_history_cannot_be_edited(): void
    {
        $f = $this->inventoryFixture();
        $receipt = app(ReceiveStock::class)->execute($f['staff'], $f['input']);
        $this->expectException(\LogicException::class);
        $receipt->update(['quantity' => 99]);
    }
}
