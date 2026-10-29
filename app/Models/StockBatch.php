<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBatch extends Model
{
    protected $fillable = ['receipt_line_id', 'product_id', 'condition', 'unit_net_cost', 'selling_price', 'sales_tax_rate', 'currency'];

    protected function casts(): array
    {
        return ['unit_net_cost' => 'decimal:4', 'selling_price' => 'decimal:2', 'sales_tax_rate' => 'decimal:4'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(ReceiptLine::class);
    }
}
