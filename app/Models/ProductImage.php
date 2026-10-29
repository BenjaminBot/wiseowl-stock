<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected static function booted(): void
    {
        static::saved(fn (ProductImage $image) => Product::whereKey($image->product_id)->increment('catalogue_version'));
    }

    protected $fillable = ['product_id', 'source', 'source_url', 'external_url', 'storage_path', 'selected_by', 'match_type', 'edition_isbn'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->storage_path ? url('/storage/'.$this->storage_path) : $this->external_url;
    }
}
