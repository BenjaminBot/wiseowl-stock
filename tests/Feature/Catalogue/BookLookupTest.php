<?php

namespace Tests\Feature\Catalogue;

use App\Models\User;
use App\Services\Books\BookLookup;
use App\Services\Books\CoverUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['book-lookup.enabled' => true, 'book-lookup.google_api_key' => null]);
        Http::preventStrayRequests();
    }

    public static function edition(): array
    {
        return ['key' => '/books/OL123M', 'isbn_13' => ['9780140328721'], 'title' => 'L-Għasfur iż-Żgħir',
            'authors' => [['name' => 'Example Author']], 'publishers' => ['Example Publisher'], 'publish_date' => '2024',
            'physical_format' => 'Paperback', 'covers' => [123, 456, -1, 123]];
    }

    public function test_exact_isbn_metadata_and_multiple_covers_are_normalised_and_cached(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response(self::edition())]);
        $first = app(BookLookup::class)->lookup('0140328726');
        $second = app(BookLookup::class)->lookup('9780140328721');
        $this->assertSame($first, $second);
        $this->assertSame('L-Għasfur iż-Żgħir', $first['suggestions'][0]['name']);
        $this->assertCount(2, $first['covers']);
        $this->assertSame('https://covers.openlibrary.org/b/id/123-M.jpg?default=false', $first['covers'][0]['url']);
        Http::assertSentCount(1);
    }

    public function test_no_match_and_provider_failure_return_manual_fallback_results(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response([], 503)]);
        $result = app(BookLookup::class)->lookup('9780140328721');
        $this->assertSame('unavailable', $result['sources']['Open Library']);
        $this->assertSame([], $result['suggestions']);
        $this->assertSame([], $result['covers']);
    }

    public function test_wrong_edition_is_not_presented_as_an_exact_match(): void
    {
        $body = self::edition();
        $body['isbn_13'] = ['9780804429573'];
        Http::fake(['openlibrary.org/*' => Http::response($body)]);
        $this->assertSame([], app(BookLookup::class)->lookup('9780140328721')['suggestions']);
    }

    public function test_google_is_optional_and_one_failed_provider_does_not_hide_another_match(): void
    {
        config(['book-lookup.google_api_key' => 'test-key-not-a-real-secret']);
        Http::fake([
            'openlibrary.org/*' => Http::response([], 503),
            'www.googleapis.com/*' => Http::response(['items' => [
                ['id' => 'valid_volume', 'volumeInfo' => ['title' => 'Matching book', 'industryIdentifiers' => [['identifier' => '0140328726']], 'imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=valid_volume&key=secret']]],
                ['id' => 'wrong_volume', 'volumeInfo' => ['title' => 'Wrong book', 'industryIdentifiers' => [['identifier' => '9780804429573']]]],
            ]]),
        ]);
        $result = app(BookLookup::class)->lookup('9780140328721', true);
        $this->assertCount(1, $result['suggestions']);
        $this->assertSame('Matching book', $result['suggestions'][0]['name']);
        $this->assertSame('https://books.google.com/books/content?id=valid_volume', $result['covers'][0]['url']);
        $this->assertStringNotContainsString('secret', json_encode($result));
    }

    public function test_cover_urls_reject_non_provider_hosts_and_unsafe_schemes(): void
    {
        foreach (['http://127.0.0.1/private', 'javascript:alert(1)', 'https://books.google.com.evil.test/books/content', 'https://user@books.google.com/books/content'] as $url) {
            $this->assertNull(CoverUrl::normalise('Google Books', $url));
        }
    }

    public function test_lookup_route_requires_staff_and_valid_isbn(): void
    {
        $this->getJson('/intake/book-lookup/9780140328721')->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/intake/book-lookup/9780140328721')->assertForbidden();
        $user->forceFill(['is_staff' => true])->save();
        $this->getJson('/intake/book-lookup/invalid')->assertUnprocessable();
        Http::fake(['openlibrary.org/*' => Http::response(self::edition())]);
        $this->getJson('/intake/book-lookup/9780140328721')->assertOk()->assertJsonPath('isbn', '9780140328721');
    }
}
