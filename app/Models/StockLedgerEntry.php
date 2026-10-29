<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedgerEntry extends ImmutableRecord
{
    protected $fillable = ['receipt_line_id', 'stock_batch_id', 'location_id', 'sales_channel_id', 'quantity_delta', 'recorded_by'];

    protected function casts(): array
    {
        return ['quantity_delta' => 'integer'];
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(ReceiptLine::class);
    }
}
