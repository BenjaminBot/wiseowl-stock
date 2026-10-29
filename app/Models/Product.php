<?php

namespace App\Models;

use App\Support\Isbn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    public function bookDetail(): HasOne
    {
        return $this->hasOne(BookDetail::class);
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(ProductImage::class);
    }

    protected $fillable = ['sku', 'name', 'description', 'type', 'isbn', 'barcode', 'category_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->exists && $product->isDirty()) {
                $product->catalogue_version = ((int) $product->getOriginal('catalogue_version')) + 1;
            }
            if (filled($product->isbn)) {
                $product->isbn = Isbn::canonical($product->isbn);
            }
        });
    }
}
