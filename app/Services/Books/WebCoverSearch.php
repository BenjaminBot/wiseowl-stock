<?php

namespace App\Services\Books;

use App\Support\Isbn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class WebCoverSearch
{
    public function __construct(private readonly LookupSettings $settings) {}

    public function search(string $input, bool $manual = true): array
    {
        $isbn = Isbn::canonical($input);
        $empty = ['isbn' => $isbn, 'covers' => [], 'status' => 'not_found'];
        if (! config('book-lookup.enabled')) {
            return array_replace($empty, ['status' => 'disabled']);
        }
        if (blank($apiKey = config('book-lookup.brave_api_key'))) {
            return array_replace($empty, ['status' => 'not_configured']);
        }
        if (! $this->settings->allows('brave', 'images', $manual)) {
            return [...$empty, 'status' => 'disabled'];
        }
        $key = 'book-web-covers:v1:'.$isbn.':'.substr(hash('sha256', $apiKey), 0, 12);
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }
        try {
            $response = RateLimiter::attempt('book-lookup:brave', 1, fn () => Http::acceptJson()
                ->withHeaders(['X-Subscription-Token' => $apiKey])->connectTimeout(5)->timeout(10)
                ->get('https://api.search.brave.com/res/v1/images/search', [
                    'q' => $isbn.' book cover', 'count' => 20, 'safesearch' => 'strict', 'spellcheck' => 'false',
                ]), decaySeconds: 1);
            if ($response === false || $response->status() === 429) {
                return array_replace($empty, ['status' => 'rate_limited']);
            }
            if (in_array($response->status(), [401, 403], true)) {
                return array_replace($empty, ['status' => 'configuration_error']);
            }
            if (! $response->successful() || ! is_array($items = $response->json('results'))) {
                return array_replace($empty, ['status' => 'unavailable']);
            }
            $covers = [];
            foreach (array_slice($items, 0, 20) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $url = CoverUrl::normalise('Brave Image Search', $item['properties']['url'] ?? null);
                $sourceUrl = CoverUrl::publicHttps($item['url'] ?? null);
                $preview = CoverUrl::publicHttps($item['thumbnail']['src'] ?? null);
                if (! $url || ! $sourceUrl || ! $preview || parse_url($preview, PHP_URL_HOST) !== 'imgs.search.brave.com') {
                    continue;
                }
                $title = is_string($item['title'] ?? null) ? mb_substr(trim(strip_tags($item['title'])), 0, 255) : 'Cover search result';
                $covers[$url] = ['key' => 'web:'.hash('sha256', 'Brave Image Search'.$url), 'source' => 'Brave Image Search',
                    'source_url' => $sourceUrl, 'source_host' => parse_url($sourceUrl, PHP_URL_HOST),
                    'url' => $url, 'preview_url' => $preview, 'title' => $title,
                    'match_type' => 'web', 'edition_isbn' => null];
                if (count($covers) >= 12) {
                    break;
                }
            }
            $result = ['isbn' => $isbn, 'covers' => array_values($covers), 'status' => $covers ? 'found' : 'not_found'];
            Cache::put($key, $result, $covers ? 21600 : 900);

            return $result;
        } catch (Throwable) {
            // Never expose provider exception text, request headers or credentials.
            return array_replace($empty, ['status' => 'unavailable']);
        }
    }

    public function cachedCover(string $input, string $candidateKey): ?array
    {
        $isbn = Isbn::canonical($input);
        if (! $this->settings->allows('brave', 'images', true) || blank($apiKey = config('book-lookup.brave_api_key'))) {
            return null;
        }
        $result = Cache::get('book-web-covers:v1:'.$isbn.':'.substr(hash('sha256', $apiKey), 0, 12), []);

        return collect($result['covers'] ?? [])->firstWhere('key', $candidateKey);
    }
}
