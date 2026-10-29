<?php

namespace App\Services\Books;

use App\Models\LookupProvider;

final class LookupSettings
{
    public function mode(string $provider, string $capability): string
    {
        $field = $capability === 'metadata' ? 'metadata_mode' : 'image_mode';

        return LookupProvider::where('key', $provider)->value($field) ?? 'disabled';
    }

    public function allows(string $provider, string $capability, bool $manual = false): bool
    {
        $definition = LookupProvider::DEFINITIONS[$provider] ?? null;
        if (! config('book-lookup.enabled') || ! ($definition[$capability] ?? false)) {
            return false;
        }
        if ($definition['credential'] && blank(config($definition['credential']))) {
            return false;
        }
        $mode = $this->mode($provider, $capability);

        return $mode === 'automatic' || ($manual && $mode === 'manual');
    }

    public function any(string $provider, bool $manual = false): bool
    {
        return $this->allows($provider, 'metadata', $manual) || $this->allows($provider, 'images', $manual);
    }

    public function signature(): string
    {
        return substr(hash('sha256', LookupProvider::orderBy('key')->get(['key', 'metadata_mode', 'image_mode'])->toJson()), 0, 12);
    }

    public function client(): array
    {
        $result = [];
        foreach (LookupProvider::DEFINITIONS as $key => $definition) {
            $result[$key] = ['name' => $definition['name'], 'metadata' => $this->mode($key, 'metadata'), 'images' => $this->mode($key, 'images'),
                'configured' => $definition['credential'] === null || filled(config($definition['credential'])),
                'available' => $this->any($key, true)];
        }

        return $result;
    }
}
