<?php

namespace App\Services\Books;

use App\Support\BookFormat;
use App\Support\BookLanguage;
use App\Support\Isbn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookLookup
{
    public function __construct(private readonly LookupSettings $settings) {}

    public function lookup(string $input, bool $includeGoogle = false, bool $includeOpenLibrary = false): array
    {
        $isbn = Isbn::canonical($input);
        if (! config('book-lookup.enabled')) {
            return ['isbn' => $isbn, 'suggestions' => [], 'covers' => [], 'sources' => ['Online lookup' => 'disabled']];
        }
        $key = $this->lookupCacheKey($isbn, $includeGoogle, $includeOpenLibrary);
        if (is_array($cached = Cache::get($key))) {
            return $this->withCachedDetails($cached);
        }
        $result = ['isbn' => $isbn, 'suggestions' => [], 'covers' => [], 'sources' => []];
        foreach (['open_library' => 'Open Library', 'google_books' => 'Google Books'] as $provider => $source) {
            $manual = $provider === 'google_books' ? $includeGoogle : $includeOpenLibrary;
            if (! $this->settings->any($provider, $manual)) {
                continue;
            }
            $rawKey = 'book-provider:v1:'.$provider.':'.$isbn.':'.substr(hash('sha256', $provider === 'google_books' ? (config('book-lookup.google_api_key') ?? '') : ''), 0, 12);
            $data = Cache::get($rawKey);
            if (! is_array($data)) {
                try {
                    $data = $provider === 'open_library' ? $this->openLibrary($isbn) : $this->googleBooks($isbn);
                } catch (Throwable) {
                    $data = ['status' => 'unavailable', 'suggestions' => [], 'covers' => []];
                }
                Cache::put($rawKey, $data, in_array($data['status'], ['unavailable', 'rate_limited'], true) ? 10 : ($data['suggestions'] ? 21600 : 900));
            }
            $result['sources'][$source] = $data['status'];
            if ($this->settings->allows($provider, 'metadata', $manual)) {
                $result['suggestions'] = [...$result['suggestions'], ...$data['suggestions']];
            }
            if ($this->settings->allows($provider, 'images', $manual)) {
                $result['covers'] = [...$result['covers'], ...$data['covers']];
            }
        }
        $result['covers'] = collect($result['covers'])->unique('url')->take(12)->values()->all();
        $failed = count(array_intersect(array_values($result['sources']), ['unavailable', 'rate_limited'])) > 0;
        Cache::put($key, $result, $failed ? 10 : ($result['suggestions'] ? 21600 : 900));

        return $this->withCachedDetails($result);
    }

    public function alternativeCovers(string $input, bool $includeGoogle = false): array
    {
        $isbn = Isbn::canonical($input);
        $openLibrary = $this->settings->allows('open_library', 'images', true)
            ? $this->openLibraryAlternativeCovers($isbn) : ['isbn' => $isbn, 'status' => 'disabled', 'covers' => []];
        if (! $this->settings->allows('google_books', 'images', $includeGoogle)) {
            $this->rememberAlternativeCandidates($isbn, $openLibrary['covers']);

            return $openLibrary;
        }
        $exact = $this->lookup($isbn, $includeGoogle, true);
        $google = $this->googleAlternativeCovers($isbn, $exact);
        $excluded = array_column($exact['covers'], 'url');
        $covers = collect([...$google['covers'], ...$openLibrary['covers']])
            ->reject(fn ($cover) => in_array($cover['url'], $excluded, true))->unique('url')->take(12)->values()->all();
        $sources = ['Open Library' => $openLibrary['status'], 'Google Books' => $google['status']];
        $failure = collect($sources)->first(fn ($status) => in_array($status, ['configuration_error', 'connection_failed', 'rate_limited', 'unavailable'], true));
        $status = $covers ? 'found' : ($failure ?: ($google['status'] === 'no_title' ? $openLibrary['status'] : 'not_found'));

        $this->rememberAlternativeCandidates($isbn, $covers);

        return ['isbn' => $isbn, 'covers' => $covers, 'status' => $status, 'sources' => $sources,
            'partial_failure' => (bool) ($covers && $failure)];
    }

    private function lookupCacheKey(string $isbn, bool $includeGoogle, bool $includeOpenLibrary = false): string
    {
        $provider = ($includeGoogle || $this->settings->any('google_books')) && filled(config('book-lookup.google_api_key'))
            ? 'google:'.substr(hash('sha256', config('book-lookup.google_api_key')), 0, 12) : 'open';

        return 'book-lookup:v7:'.$isbn.':'.$provider.':'.(int) $includeGoogle.':'.(int) $includeOpenLibrary.':'.$this->settings->signature();
    }

    public function cachedLookup(string $input, bool $includeGoogle = false, bool $includeOpenLibrary = false): array
    {
        return $this->withCachedDetails(Cache::get($this->lookupCacheKey(Isbn::canonical($input), $includeGoogle, $includeOpenLibrary), ['suggestions' => [], 'covers' => []]));
    }

    public function cachedSuggestion(string $isbn, string $key): ?array
    {
        foreach ([[false, false], [true, false], [false, true], [true, true]] as [$google, $openLibrary]) {
            $found = collect($this->cachedLookup($isbn, $google, $openLibrary)['suggestions'])->firstWhere('key', $key);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    public function cachedExactCover(string $isbn, string $key): ?array
    {
        foreach ([[false, false], [true, false], [false, true], [true, true]] as [$google, $openLibrary]) {
            $found = collect($this->cachedLookup($isbn, $google, $openLibrary)['covers'])->firstWhere('key', $key);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function withCachedDetails(array $result): array
    {
        $cached = isset($result['isbn']) ? Cache::get('book-author-names:v1:'.$result['isbn']) : null;
        $description = isset($result['isbn']) ? Cache::get('book-description:v1:'.$result['isbn']) : null;
        foreach ($result['suggestions'] as &$book) {
            foreach (['language' => BookLanguage::class, 'format' => BookFormat::class] as $field => $normaliser) {
                $book[$field.'_original'] ??= $book[$field] ?? null;
                $book[$field] = $normaliser::normalise($book[$field] ?? null);
            }
            if ($book['source'] !== 'Open Library') {
                continue;
            }
            if (is_array($description)) {
                if (filled($description['description'])) {
                    $book['description'] = $description['description'];
                }
                $book['description_status'] = $description['status'];
            }
            if (is_array($cached)) {
                if ($cached['authors']) {
                    $book['authors'] = $cached['authors'];
                }
                $book['author_status'] = $cached['status'];
            } elseif (! $book['authors']) {
                $known = collect($book['author_keys'] ?? [])->map(fn ($key) => Cache::get('book-author:v1:'.$key))
                    ->filter(fn ($author) => is_array($author) && filled($author['name'] ?? null))->pluck('name')->unique()->values()->all();
                if ($known && count($known) === count($book['author_keys'])) {
                    $book['authors'] = $known;
                    $book['author_status'] = 'found';
                }
            }
        }

        return $result;
    }

    public function authorDetails(string $input, bool $manualOpenLibrary = false): array
    {
        $isbn = Isbn::canonical($input);
        $empty = ['isbn' => $isbn, 'status' => 'not_found', 'authors' => []];
        if (! $this->settings->allows('open_library', 'metadata', $manualOpenLibrary)) {
            return [...$empty, 'status' => 'disabled', 'suggestions' => []];
        }
        $exact = $this->lookup($isbn, false, $manualOpenLibrary);
        $book = collect($exact['suggestions'])->firstWhere('source', 'Open Library');
        if (! $book) {
            return [...$empty, 'suggestions' => []];
        }
        if (filled($book['authors']) && ($book['author_status'] ?? '') === 'found') {
            return ['isbn' => $isbn, 'status' => 'found', 'authors' => $book['authors'], 'suggestions' => $exact['suggestions']];
        }
        $key = 'book-author-names:v1:'.$isbn;
        $result = Cache::get($key);
        if (! is_array($result)) {
            try {
                $response = RateLimiter::attempt('book-lookup:open-library', 1, fn () => Http::acceptJson()
                    ->withUserAgent(config('book-lookup.user_agent'))->connectTimeout(2)->timeout(config('book-lookup.timeout_seconds'))
                    ->get('https://openlibrary.org/search.json', ['q' => 'isbn:'.$isbn, 'fields' => 'key,author_name,isbn', 'limit' => 1]), decaySeconds: 1);
                if ($response === false || $response->status() === 429) {
                    return [...$empty, 'status' => 'rate_limited', 'suggestions' => $exact['suggestions']];
                }
                if (! $response->successful() || ! is_array($docs = $response->json('docs'))) {
                    $result = [...$empty, 'status' => 'unavailable'];
                } else {
                    $match = collect(array_slice($docs, 0, 1))->first(fn ($doc) => is_array($doc)
                        && ((filled($book['work_key'] ?? null) && ($doc['key'] ?? null) === $book['work_key'])
                            || $this->matches(is_array($doc['isbn'] ?? null) ? $doc['isbn'] : [], $isbn)));
                    $authors = collect(is_array($match['author_name'] ?? null) ? $match['author_name'] : [])
                        ->take(10)->map(fn ($name) => $this->text($name))->filter()->unique()->values()->all();
                    $result = ['isbn' => $isbn, 'status' => $authors ? 'found' : 'not_found', 'authors' => $authors];
                }
            } catch (Throwable) {
                $result = [...$empty, 'status' => 'unavailable'];
            }
            Cache::put($key, $result, $result['status'] === 'found' ? 21600 : ($result['status'] === 'not_found' ? 900 : 10));
        }

        return [...$result, 'suggestions' => $this->withCachedDetails($exact)['suggestions']];
    }

    public function descriptionDetails(string $input, bool $manualOpenLibrary = false): array
    {
        $isbn = Isbn::canonical($input);
        $empty = ['isbn' => $isbn, 'status' => 'not_found', 'description' => null];
        if (! $this->settings->allows('open_library', 'metadata', $manualOpenLibrary)) {
            return [...$empty, 'status' => 'disabled', 'suggestions' => []];
        }
        $exact = $this->lookup($isbn, false, $manualOpenLibrary);
        $book = collect($exact['suggestions'])->firstWhere('source', 'Open Library');
        if (! $book) {
            return [...$empty, 'suggestions' => []];
        }
        if (filled($book['description'] ?? null)) {
            return ['isbn' => $isbn, 'status' => 'found', 'description' => $book['description'], 'suggestions' => $exact['suggestions']];
        }
        $workKey = $book['work_key'] ?? null;
        if (! is_string($workKey) || ! preg_match('#^/works/OL[0-9]+W$#', $workKey)) {
            return [...$empty, 'suggestions' => $exact['suggestions']];
        }
        $key = 'book-description:v1:'.$isbn;
        $result = Cache::get($key);
        if (! is_array($result)) {
            try {
                $response = RateLimiter::attempt('book-lookup:open-library', 1, fn () => Http::acceptJson()
                    ->withUserAgent(config('book-lookup.user_agent'))->connectTimeout(2)->timeout(config('book-lookup.timeout_seconds'))
                    ->get('https://openlibrary.org'.$workKey.'.json'), decaySeconds: 1);
                if ($response === false || $response->status() === 429) {
                    return [...$empty, 'status' => 'rate_limited', 'suggestions' => $exact['suggestions']];
                }
                if (! $response->successful() || $response->json('key') !== $workKey) {
                    $result = [...$empty, 'status' => 'unavailable'];
                } else {
                    $description = $this->descriptionText($response->json('description'));
                    $result = ['isbn' => $isbn, 'status' => $description ? 'found' : 'not_found', 'description' => $description];
                }
            } catch (Throwable) {
                $result = [...$empty, 'status' => 'unavailable'];
            }
            Cache::put($key, $result, $result['status'] === 'found' ? 21600 : ($result['status'] === 'not_found' ? 900 : 10));
        }

        return [...$result, 'suggestions' => $this->withCachedDetails($exact)['suggestions']];
    }

    private function descriptionText(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['value'] ?? null;
        }
        if (! is_string($value)) {
            return null;
        }
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('#<(script|style)\\b[^>]*>.*?</\\1>#is', '', $value);
        $value = preg_replace('#<br\\s*/?>|</p>#i', "\n", $value);
        $value = trim(strip_tags($value));

        return $value !== '' ? mb_substr($value, 0, 20000) : null;
    }

    private function candidateCacheKey(string $isbn): string
    {
        return 'book-cover-candidates:v1:'.$this->settings->signature().':'.$isbn.':'.substr(hash('sha256', config('book-lookup.google_api_key') ?? ''), 0, 12);
    }

    private function rememberAlternativeCandidates(string $isbn, array $covers): void
    {
        $key = $this->candidateCacheKey($isbn);
        $previous = Cache::get($key, []);
        Cache::put($key, collect([...$previous, ...$covers])->unique('key')->take(36)->values()->all(), 21600);
    }

    public function cachedAlternativeCover(string $input, string $key): ?array
    {
        return collect(Cache::get($this->candidateCacheKey(Isbn::canonical($input)), []))->firstWhere('key', $key);
    }

    private function googleAlternativeCovers(string $isbn, array $exact): array
    {
        $empty = ['covers' => [], 'status' => 'not_found'];
        $suggestions = collect($exact['suggestions']);
        $seed = $suggestions->first(fn ($book) => filled($book['name'] ?? null) && filled($book['authors'][0] ?? null))
            ?? $suggestions->first(fn ($book) => filled($book['name'] ?? null));
        if (! $seed) {
            return array_replace($empty, ['status' => 'no_title']);
        }
        $title = $this->text($seed['name'], 180);
        $author = $this->text($seed['authors'][0] ?? null, 100);
        if (! $author && filled($seed['author_key'] ?? null)) {
            $resolved = $this->openLibraryAuthor($seed['author_key']);
            if ($resolved['status'] === 'rate_limited') {
                return array_replace($empty, ['status' => 'rate_limited']);
            }
            $author = $resolved['name'];
        }
        $query = $title.($author ? ' '.Str::ascii($author) : '');
        $key = 'book-google-alternatives:v1:'.hash('sha256', $isbn.$query.config('book-lookup.google_api_key'));
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }
        try {
            $response = Http::acceptJson()->connectTimeout(config('book-lookup.alternative_connect_timeout_seconds'))
                ->timeout(config('book-lookup.alternative_timeout_seconds'))
                ->get('https://www.googleapis.com/books/v1/volumes', ['q' => $query, 'maxResults' => 12, 'key' => config('book-lookup.google_api_key')]);
            if ($response->status() === 429) {
                return array_replace($empty, ['status' => 'rate_limited']);
            }
            if (in_array($response->status(), [400, 401, 403], true)) {
                return array_replace($empty, ['status' => 'configuration_error']);
            }
            if (! $response->successful() || ! is_array($body = $response->json())
                || (isset($body['items']) && ! is_array($body['items']))) {
                return array_replace($empty, ['status' => 'unavailable']);
            }
            $covers = [];
            foreach (array_slice($body['items'] ?? [], 0, 12) as $item) {
                if (! is_array($item) || ! is_array($volume = $item['volumeInfo'] ?? null)) {
                    continue;
                }
                $candidateTitle = $this->text($volume['title'] ?? null);
                $id = $item['id'] ?? null;
                if (! $candidateTitle || ! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $id)
                    || ! $this->similarTitle($title, $candidateTitle)) {
                    continue;
                }
                $identifiers = collect(is_array($volume['industryIdentifiers'] ?? null) ? $volume['industryIdentifiers'] : [])->pluck('identifier')->all();
                if ($this->matches($identifiers, $isbn)) {
                    continue;
                }
                $editionIsbn = null;
                foreach ($identifiers as $identifier) {
                    if (! is_string($identifier)) {
                        continue;
                    }
                    try {
                        $editionIsbn = Isbn::canonical($identifier);
                        break;
                    } catch (ValidationException) {
                    }
                }
                $cover = $this->cover('Google Books', $volume['imageLinks']['thumbnail'] ?? null,
                    'https://books.google.com/books?id='.rawurlencode($id), $candidateTitle);
                if (! $cover) {
                    continue;
                }
                $authors = collect(is_array($volume['authors'] ?? null) ? $volume['authors'] : [])
                    ->take(10)->map(fn ($value) => $this->text($value))->filter()->implode(', ');
                $covers[] = [...$cover, 'match_type' => 'alternative', 'match_basis' => 'title', 'edition_isbn' => $editionIsbn,
                    'edition_description' => implode(' · ', array_filter([$authors, $this->text($volume['publisher'] ?? null),
                        $this->text($volume['publishedDate'] ?? null, 64), $this->text($volume['language'] ?? null, 64)]))];
            }
            $result = ['covers' => collect($covers)->unique('url')->values()->all(), 'status' => $covers ? 'found' : 'not_found'];
            Cache::put($key, $result, $covers ? 21600 : 900);

            return $result;
        } catch (ConnectionException) {
            return array_replace($empty, ['status' => 'connection_failed']);
        } catch (Throwable) {
            return array_replace($empty, ['status' => 'unavailable']);
        }
    }

    private function openLibraryAuthor(string $key): array
    {
        $empty = ['status' => 'unavailable', 'name' => null];
        if (! preg_match('#^/authors/OL[0-9]+A$#', $key)) {
            return $empty;
        }
        $cacheKey = 'book-author:v1:'.$key;
        if (is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }
        try {
            $response = RateLimiter::attempt('book-lookup:open-library', 1, fn () => Http::acceptJson()
                ->withUserAgent(config('book-lookup.user_agent'))->connectTimeout(2)->timeout(config('book-lookup.timeout_seconds'))
                ->get('https://openlibrary.org'.$key.'.json'), decaySeconds: 1);
            if ($response === false) {
                return ['status' => 'rate_limited', 'name' => null];
            }
            $name = $response->successful() ? $this->text($response->json('name'), 100) : null;
            $result = ['status' => $name ? 'found' : 'unavailable', 'name' => $name];
            Cache::put($cacheKey, $result, $name ? 86400 : 15);

            return $result;
        } catch (Throwable) {
            return $empty;
        }
    }

    private function similarTitle(string $expected, string $candidate): bool
    {
        $normalise = fn ($value) => trim(preg_replace('/[^\\pL\\pN]+/u', ' ', mb_strtolower($value)));
        $expected = $normalise(explode(':', $expected, 2)[0]);
        $candidate = $normalise(explode(':', $candidate, 2)[0]);

        return mb_strlen($expected) >= 3 && $expected === $candidate;
    }

    private function openLibraryAlternativeCovers(string $input): array
    {
        $isbn = Isbn::canonical($input);
        $empty = ['isbn' => $isbn, 'covers' => [], 'status' => 'not_found'];
        if (! config('book-lookup.enabled')) {
            return array_replace($empty, ['status' => 'disabled']);
        }
        $key = 'book-open-library-alternatives:v1:'.$isbn;
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }
        $exact = $this->lookup($isbn, false, true);
        $seed = $exact['suggestions'] ?: (Cache::get('book-provider:v1:open_library:'.$isbn.':'.substr(hash('sha256', ''), 0, 12))['suggestions'] ?? []);
        $work = collect($seed)->first(fn ($book) => filled($book['work_key'] ?? null));
        if (! $work) {
            return array_replace($empty, ['status' => 'no_linked_work']);
        }
        try {
            $response = RateLimiter::attempt('book-lookup:open-library', 1, fn () => Http::acceptJson()
                ->withUserAgent(config('book-lookup.user_agent'))->connectTimeout(config('book-lookup.alternative_connect_timeout_seconds'))->timeout(config('book-lookup.alternative_timeout_seconds'))
                ->get('https://openlibrary.org'.$work['work_key'].'/editions.json', ['limit' => 50]), decaySeconds: 1);
            if ($response === false) {
                return array_replace($empty, ['status' => 'rate_limited']);
            }
            if ($response->status() === 429) {
                return array_replace($empty, ['status' => 'rate_limited']);
            }
            if (! $response->successful() || ! is_array($body = $response->json()) || ! is_array($body['entries'] ?? null)) {
                return array_replace($empty, ['status' => 'unavailable']);
            }
            $covers = [];
            $seen = array_fill_keys(array_column($exact['covers'], 'url'), true);
            foreach (array_slice(is_array($body['entries'] ?? null) ? $body['entries'] : [], 0, 50) as $edition) {
                if (! is_array($edition) || ! is_string($editionKey = $edition['key'] ?? null) || ! preg_match('#^/books/OL[0-9]+M$#', $editionKey)) {
                    continue;
                }
                $identifiers = [...(is_array($edition['isbn_13'] ?? null) ? $edition['isbn_13'] : []), ...(is_array($edition['isbn_10'] ?? null) ? $edition['isbn_10'] : [])];
                // Exact matches stay in the exact-ISBN group, never the alternative group.
                if ($this->matches($identifiers, $isbn)) {
                    continue;
                }
                $editionIsbn = null;
                foreach (array_slice($identifiers, 0, 100) as $identifier) {
                    if (! is_string($identifier)) {
                        continue;
                    }
                    try {
                        $editionIsbn = Isbn::canonical($identifier);
                        break;
                    } catch (ValidationException) {
                    }
                }
                foreach (array_slice(is_array($edition['covers'] ?? null) ? $edition['covers'] : [], 0, 4) as $id) {
                    if ((! is_int($id) && ! is_string($id)) || ! preg_match('/^[1-9][0-9]*$/', (string) $id)) {
                        continue;
                    }
                    $cover = $this->cover('Open Library', 'https://covers.openlibrary.org/b/id/'.$id.'-M.jpg?default=false', 'https://openlibrary.org'.$editionKey, $this->text($edition['title'] ?? null) ?? $work['name']);
                    if (! $cover || isset($seen[$cover['url']])) {
                        continue;
                    }
                    $seen[$cover['url']] = true;
                    $covers[] = [...$cover, 'match_type' => 'alternative', 'edition_isbn' => $editionIsbn,
                        'edition_description' => implode(' · ', array_filter([
                            $this->text(is_array($edition['publishers'] ?? null) ? ($edition['publishers'][0] ?? null) : null),
                            $this->text($edition['publish_date'] ?? null, 64), $this->text($edition['physical_format'] ?? null),
                        ]))];
                    if (count($covers) >= 12) {
                        break 2;
                    }
                }
            }
            $result = ['isbn' => $isbn, 'covers' => $covers, 'status' => $covers ? 'found' : 'not_found'];
            Cache::put($key, $result, $covers ? 21600 : 900);

            return $result;
        } catch (ConnectionException) {
            return array_replace($empty, ['status' => 'connection_failed']);
        } catch (Throwable) {
            return array_replace($empty, ['status' => 'unavailable']);
        }
    }

    private function openLibrary(string $isbn): array
    {
        $empty = ['status' => 'not_found', 'suggestions' => [], 'covers' => []];
        $response = RateLimiter::attempt('book-lookup:open-library', 1, fn () => Http::acceptJson()
            ->withUserAgent(config('book-lookup.user_agent'))->connectTimeout(2)->timeout(config('book-lookup.timeout_seconds'))
            ->get('https://openlibrary.org/isbn/'.$isbn.'.json'), decaySeconds: 1);
        if ($response === false) {
            return array_replace($empty, ['status' => 'rate_limited']);
        }
        if ($response->status() === 404) {
            return $empty;
        }
        if (! $response->successful() || ! is_array($body = $response->json())) {
            return array_replace($empty, ['status' => 'unavailable']);
        }
        $identifiers = [...(is_array($body['isbn_13'] ?? null) ? $body['isbn_13'] : []), ...(is_array($body['isbn_10'] ?? null) ? $body['isbn_10'] : [])];
        if (! $this->matches($identifiers, $isbn) || ! $title = $this->text($body['title'] ?? null)) {
            return $empty;
        }
        $edition = is_string($body['key'] ?? null) && preg_match('#^/books/OL[0-9]+M$#', $body['key']) ? $body['key'] : '/isbn/'.$isbn;
        $sourceUrl = 'https://openlibrary.org'.$edition;
        $book = $this->book('Open Library', $sourceUrl, $title, [
            'authors' => collect(is_array($body['authors'] ?? null) ? $body['authors'] : [])->map(fn ($author) => is_array($author) ? $this->text($author['name'] ?? null) : null)->filter()->values()->all(),
            'publisher' => $this->text(is_array($body['publishers'] ?? null) ? ($body['publishers'][0] ?? null) : null),
            'published_date' => $this->text($body['publish_date'] ?? null, 64),
            'format' => $this->text($body['physical_format'] ?? null),
            'language' => $this->text(is_array($body['languages'] ?? null) ? basename($body['languages'][0]['key'] ?? '') : null, 64),
        ]);
        $workKey = $body['works'][0]['key'] ?? null;
        $book['work_key'] = is_string($workKey) && preg_match('#^/works/OL[0-9]+W$#', $workKey) ? $workKey : null;
        $authorKey = $body['authors'][0]['key'] ?? null;
        $book['author_key'] = is_string($authorKey) && preg_match('#^/authors/OL[0-9]+A$#', $authorKey) ? $authorKey : null;
        $book['author_keys'] = collect(is_array($body['authors'] ?? null) ? $body['authors'] : [])->take(10)
            ->map(fn ($author) => is_array($author) ? ($author['key'] ?? null) : null)
            ->filter(fn ($key) => is_string($key) && preg_match('#^/authors/OL[0-9]+A$#', $key))->unique()->values()->all();
        $book['author_status'] = $book['authors'] ? 'found' : 'pending';
        $book['description'] = $this->descriptionText($body['description'] ?? null);
        $book['description_status'] = $book['description'] ? 'found' : ($book['work_key'] ? 'pending' : 'not_found');
        $covers = [];
        foreach (array_slice(is_array($body['covers'] ?? null) ? $body['covers'] : [], 0, 8) as $id) {
            if ((is_int($id) || is_string($id)) && preg_match('/^[1-9][0-9]*$/', (string) $id)) {
                $covers[] = $this->cover('Open Library', 'https://covers.openlibrary.org/b/id/'.$id.'-M.jpg?default=false', $sourceUrl, $title);
            }
        }
        if (! $covers) {
            $covers[] = $this->cover('Open Library', 'https://covers.openlibrary.org/b/isbn/'.$isbn.'-M.jpg?default=false', $sourceUrl, $title);
        }

        return ['status' => 'found', 'suggestions' => [$book], 'covers' => array_values(array_filter($covers))];
    }

    private function googleBooks(string $isbn): array
    {
        $response = Http::acceptJson()->connectTimeout(2)->timeout(config('book-lookup.timeout_seconds'))
            ->get('https://www.googleapis.com/books/v1/volumes', ['q' => 'isbn:'.$isbn, 'maxResults' => 10, 'key' => config('book-lookup.google_api_key')]);
        if (! $response->successful() || ! is_array($body = $response->json())) {
            return ['status' => 'unavailable', 'suggestions' => [], 'covers' => []];
        }
        $books = [];
        $covers = [];
        foreach (array_slice(is_array($body['items'] ?? null) ? $body['items'] : [], 0, 10) as $item) {
            if (! is_array($item) || ! is_array($volume = $item['volumeInfo'] ?? null)) {
                continue;
            }
            $identifiers = collect(is_array($volume['industryIdentifiers'] ?? null) ? $volume['industryIdentifiers'] : [])->pluck('identifier')->all();
            $id = $item['id'] ?? null;
            if (! $this->matches($identifiers, $isbn) || ! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $id) || ! $title = $this->text($volume['title'] ?? null)) {
                continue;
            }
            $sourceUrl = 'https://books.google.com/books?id='.rawurlencode($id);
            $books[] = $this->book('Google Books', $sourceUrl, $title, [
                'authors' => collect(is_array($volume['authors'] ?? null) ? $volume['authors'] : [])->take(10)->map(fn ($author) => $this->text($author))->filter()->values()->all(),
                'publisher' => $this->text($volume['publisher'] ?? null), 'published_date' => $this->text($volume['publishedDate'] ?? null, 64),
                'format' => null, 'language' => $this->text($volume['language'] ?? null, 64),
                'description' => $this->descriptionText($volume['description'] ?? null),
            ]);
            if ($cover = $this->cover('Google Books', $volume['imageLinks']['thumbnail'] ?? null, $sourceUrl, $title)) {
                $covers[] = $cover;
            }
        }

        return ['status' => $books ? 'found' : 'not_found', 'suggestions' => $books, 'covers' => $covers];
    }

    private function matches(array $identifiers, string $isbn): bool
    {
        foreach (array_slice($identifiers, 0, 100) as $identifier) {
            if (! is_string($identifier)) {
                continue;
            }
            try {
                if (Isbn::canonical($identifier) === $isbn) {
                    return true;
                }
            } catch (ValidationException) {
            }
        }

        return false;
    }

    private function text(mixed $value, int $limit = 255): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = mb_substr(trim(strip_tags($value)), 0, $limit);

        return $value !== '' ? $value : null;
    }

    private function book(string $source, string $url, string $title, array $details): array
    {
        return ['key' => hash('sha256', $source.$url), 'source' => $source, 'source_url' => $url, 'name' => $title, ...$details];
    }

    private function cover(string $source, mixed $url, string $sourceUrl, string $title): ?array
    {
        $url = CoverUrl::normalise($source, $url);

        return $url ? ['key' => hash('sha256', $source.$url), 'source' => $source, 'source_url' => $sourceUrl, 'url' => $url, 'title' => $title, 'match_type' => 'exact', 'edition_isbn' => null] : null;
    }
}
