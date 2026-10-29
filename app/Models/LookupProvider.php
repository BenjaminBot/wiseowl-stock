<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class LookupProvider extends Model
{
    protected $fillable = ['metadata_mode', 'image_mode', 'updated_by'];

    public const DEFINITIONS = [
        'open_library' => ['name' => 'Open Library', 'metadata' => true, 'images' => true, 'credential' => null, 'kind' => 'Book catalogue'],
        'google_books' => ['name' => 'Google Books', 'metadata' => true, 'images' => true, 'credential' => 'book-lookup.google_api_key', 'kind' => 'Book catalogue'],
        'brave' => ['name' => 'Brave Image Search', 'metadata' => false, 'images' => true, 'credential' => 'book-lookup.brave_api_key', 'kind' => 'Web image search'],
    ];

    protected static function booted(): void
    {
        static::saving(function (LookupProvider $provider) {
            $definition = self::DEFINITIONS[$provider->key] ?? null;
            if (! $definition || ($provider->exists && $provider->isDirty('key'))) {
                throw ValidationException::withMessages(['key' => 'Only registered lookup providers can be configured.']);
            }
            foreach (['metadata_mode' => 'metadata', 'image_mode' => 'images'] as $field => $capability) {
                if (! in_array($provider->$field, ['automatic', 'manual', 'disabled'], true)
                    || (! $definition[$capability] && $provider->$field !== 'disabled')) {
                    throw ValidationException::withMessages([$field => 'Choose a supported lookup mode.']);
                }
            }
        });
    }

    public function getCredentialStatusAttribute(): string
    {
        $key = self::DEFINITIONS[$this->key]['credential'] ?? null;

        return $key === null ? 'No key required' : (filled(config($key)) ? 'Key configured' : 'Key missing');
    }
}
