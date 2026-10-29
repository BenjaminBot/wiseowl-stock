<?php

namespace App\Services\Books;

final class CoverUrl
{
    public static function normalise(string $source, mixed $url): ?string
    {
        if ($source === 'Brave Image Search') {
            return self::publicHttps($url);
        }
        if (! is_string($url) || strlen($url) > 2048) {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }
        $host = rtrim(strtolower($parts['host'] ?? ''), '.');
        $path = $parts['path'] ?? '';
        $allowed = match ($source) {
            'Open Library' => $host === 'covers.openlibrary.org' && preg_match('#^/b/(id|isbn)/[0-9]+-[SML]\.jpg$#', $path),
            'Google Books' => ($host === 'books.google.com' && $path === '/books/content') || $host === 'books.googleusercontent.com',
            default => false,
        };
        if (! $allowed) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        unset($query['key'], $query['access_token']);

        return 'https://'.$host.$path.($query ? '?'.http_build_query($query) : '');
    }

    public static function publicHttps(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 2048 || preg_match('/[\\x00-\\x20\\x7f\\\\]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        $host = rtrim(strtolower($parts['host'] ?? ''), '.');
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || ! preg_match('/^[a-z][a-z0-9-]{1,62}$/', substr($host, strrpos($host, '.') + 1))
            || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || preg_match('/\\.(local|localhost|internal|test|invalid|example|home|lan|arpa)$/', $host)) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $name) {
            if (in_array(strtolower($name), ['key', 'api_key', 'apikey', 'access_token', 'x-subscription-token'], true)) {
                unset($query[$name]);
            }
        }

        return 'https://'.$host.($parts['path'] ?? '/').($query ? '?'.http_build_query($query) : '');
    }
}
