<?php

namespace App\Models;

use App\Support\BookFormat;
use App\Support\BookLanguage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookDetail extends Model
{
    protected static function booted(): void
    {
        static::saving(function (BookDetail $detail) {
            foreach (['language' => BookLanguage::class, 'format' => BookFormat::class] as $field => $normaliser) {
                if ($detail->isDirty($field)) {
                    $original = $detail->{$field};
                    $detail->{$field} = $normaliser::normalise($original);
                    if (filled($original) && $detail->{$field} === null) {
                        $detail->{$field.'_original'} = $original;
                        $detail->needs_review = true;
                    }
                }
            }
        });
        static::saved(fn (BookDetail $detail) => Product::whereKey($detail->product_id)->increment('catalogue_version'));
    }

    protected $fillable = ['product_id', 'author_names', 'publisher', 'published_date', 'format', 'language', 'format_original', 'language_original', 'metadata_source', 'source_url', 'needs_review', 'updated_by'];

    protected function casts(): array
    {
        return ['author_names' => 'array', 'needs_review' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
