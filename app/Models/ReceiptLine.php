<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptLine extends ImmutableRecord
{
    protected $fillable = ['idempotency_key', 'request_hash', 'intake_session_id', 'product_id', 'received_by', 'quantity', 'condition', 'cost_input', 'cost_basis', 'cost_mode', 'unit_net_cost', 'purchase_tax_rate', 'selling_price', 'sales_tax_rate', 'currency'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'cost_input' => 'decimal:4', 'unit_net_cost' => 'decimal:4', 'selling_price' => 'decimal:2', 'purchase_tax_rate' => 'decimal:4', 'sales_tax_rate' => 'decimal:4'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function intakeSession(): BelongsTo
    {
        return $this->belongsTo(IntakeSession::class);
    }
}
