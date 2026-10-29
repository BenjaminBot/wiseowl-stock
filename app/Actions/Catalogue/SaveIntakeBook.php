<?php

namespace App\Actions\Catalogue;

use App\Models\Product;
use App\Models\User;
use App\Services\Books\CoverUrl;
use App\Support\BookFormat;
use App\Support\BookLanguage;
use App\Support\Isbn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveIntakeBook
{
    public function execute(User $actor, string $isbn, ?int $productId, ?int $version, array $input, ?array $cover, string $coverChoice): Product
    {
        $isbn = Isbn::canonical($isbn);
        $existing = $productId ? Product::findOrFail($productId) : null;
        Gate::forUser($actor)->authorize($existing ? 'update' : 'create', $existing ?? Product::class);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:20000'], 'author_names' => ['nullable', 'array', 'max:10'],
            'author_names.*' => ['required', 'string', 'max:255'], 'publisher' => ['nullable', 'string', 'max:255'],
            'published_date' => ['nullable', 'string', 'max:64'], 'format' => ['nullable', Rule::in(array_keys(BookFormat::options()))],
            'language' => ['nullable', Rule::in(array_keys(BookLanguage::options()))],
            'format_original' => ['nullable', 'string', 'max:255'], 'language_original' => ['nullable', 'string', 'max:64'], 'needs_review' => ['required', 'boolean'],
            'metadata_source' => ['nullable', 'string', 'max:255'], 'source_url' => ['nullable', 'url:https', 'max:2048'],
            'cover_upload' => ['nullable', 'string'],
        ])->validate();
        if (! in_array($coverChoice, ['keep', 'defer', 'candidate', 'upload'], true)) {
            throw ValidationException::withMessages(['coverChoice' => 'Select a cover option.']);
        }
        if ($coverChoice === 'candidate' && (! $cover || ! CoverUrl::normalise($cover['source'] ?? '', $cover['url'] ?? null))) {
            throw ValidationException::withMessages(['coverChoice' => 'Choose a valid provider cover.']);
        }
        if ($coverChoice === 'upload' && (! preg_match('#^book-covers/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$#', $data['cover_upload'] ?? '') || ! Storage::disk('public')->exists($data['cover_upload']))) {
            throw ValidationException::withMessages(['catalogueData.cover_upload' => 'Upload a JPEG, PNG or WebP cover first.']);
        }

        return DB::transaction(function () use ($actor, $isbn, $existing, $version, $data, $cover, $coverChoice) {
            if ($existing) {
                $product = Product::whereKey($existing->id)->lockForUpdate()->firstOrFail();
                if ($product->isbn !== $isbn || (int) $product->catalogue_version !== $version) {
                    throw ValidationException::withMessages(['catalogueData.name' => 'This catalogue entry changed. Scan it again before saving your reviewed details.']);
                }
                $product->name = $data['name'];
                if (array_key_exists('description', $data)) {
                    $product->description = $data['description'];
                }
                $product->catalogue_version = ((int) $product->catalogue_version) + 1;
                $product->save();
            } else {
                $product = Product::firstOrCreate(['isbn' => $isbn], ['sku' => 'BOOK-'.Str::ulid(), 'name' => $data['name'], 'description' => $data['description'] ?? null, 'type' => 'book']);
                if (! $product->wasRecentlyCreated) {
                    return $product;
                }
            }
            $product->bookDetail()->updateOrCreate([], [
                'author_names' => $data['author_names'] ?? [], 'publisher' => $data['publisher'] ?? null,
                'published_date' => $data['published_date'] ?? null, 'format' => $data['format'] ?? null, 'language' => $data['language'] ?? null,
                'language_original' => $data['language_original'] ?? null, 'format_original' => $data['format_original'] ?? null,
                'needs_review' => $data['needs_review'], 'metadata_source' => $data['metadata_source'] ?? null,
                'source_url' => $data['source_url'] ?? null, 'updated_by' => $actor->id,
            ]);
            if ($coverChoice === 'candidate') {
                $product->coverImage()->updateOrCreate([], ['source' => $cover['source'], 'source_url' => $cover['source_url'],
                    'external_url' => CoverUrl::normalise($cover['source'], $cover['url']), 'storage_path' => null, 'selected_by' => $actor->id,
                    'match_type' => $cover['match_type'] ?? 'exact', 'edition_isbn' => $cover['edition_isbn'] ?? null]);
            } elseif ($coverChoice === 'upload') {
                $product->coverImage()->updateOrCreate([], ['source' => 'Own upload', 'source_url' => null, 'external_url' => null,
                    'storage_path' => $data['cover_upload'], 'selected_by' => $actor->id, 'match_type' => 'upload', 'edition_isbn' => null]);
            }

            // Keep/defer never erases a previously selected cover.
            return $product->fresh();
        });
    }
}
