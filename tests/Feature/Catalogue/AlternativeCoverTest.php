<?php

namespace Tests\Feature\Catalogue;

use App\Filament\Pages\ReceiveDelivery;
use App\Services\Books\BookLookup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class AlternativeCoverTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    private array $entries = [];

    private int $status = 200;

    private bool $linkedWork = true;

    private bool $failConnection = false;

    private bool $malformed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['book-lookup.enabled' => true, 'book-lookup.google_api_key' => null]);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/works/OL99W/editions.json')) {
                if ($this->failConnection) {
                    throw new ConnectionException('Test timeout');
                }

                return Http::response($this->malformed ? [] : ['entries' => $this->entries], $this->status);
            }
            $edition = BookLookupTest::edition();
            $edition['works'] = $this->linkedWork ? [['key' => '/works/OL99W']] : [];

            return Http::response($edition);
        });
        $this->entries = [
            ['key' => '/books/OL44M', 'title' => 'Another edition', 'isbn_13' => ['9780804429573'],
                'publishers' => ['Other publisher'], 'publish_date' => '2020', 'covers' => [123, 789, 789, -1]],
            ['key' => '/books/OL45M', 'title' => 'Exact ISBN must not become an alternative', 'isbn_13' => ['9780140328721'], 'covers' => [999]],
            ['key' => 'https://evil.test/book', 'title' => 'Invalid edition', 'covers' => [1000]],
        ];
    }

    private function primeExact(): array
    {
        $exact = app(BookLookup::class)->lookup('9780140328721');
        RateLimiter::clear('book-lookup:open-library');

        return $exact;
    }

    public function test_linked_edition_covers_are_labelled_deduplicated_and_cached_separately(): void
    {
        $exact = $this->primeExact();
        $this->assertSame('/works/OL99W', $exact['suggestions'][0]['work_key']);
        $result = app(BookLookup::class)->alternativeCovers('0140328726');
        $this->assertCount(1, $result['covers']);
        $cover = $result['covers'][0];
        $this->assertSame('alternative', $cover['match_type']);
        $this->assertSame('9780804429573', $cover['edition_isbn']);
        $this->assertSame('https://openlibrary.org/books/OL44M', $cover['source_url']);
        $this->assertStringContainsString('Other publisher', $cover['edition_description']);
        $this->assertSame($result, app(BookLookup::class)->alternativeCovers('9780140328721'));
        $this->assertSame($exact, app(BookLookup::class)->lookup('9780140328721'));
        Http::assertSentCount(2);
    }

    public function test_provider_failure_and_rate_limit_do_not_discard_exact_covers(): void
    {
        $exact = app(BookLookup::class)->lookup('9780140328721');
        $this->assertSame('rate_limited', app(BookLookup::class)->alternativeCovers('9780140328721')['status']);
        RateLimiter::clear('book-lookup:open-library');
        $this->status = 503;
        $this->assertSame('unavailable', app(BookLookup::class)->alternativeCovers('9780140328721')['status']);
        $this->assertSame($exact, app(BookLookup::class)->lookup('9780140328721'));
    }

    public function test_unlinked_work_does_not_guess_other_books_from_title(): void
    {
        $this->linkedWork = false;
        $this->assertSame('no_linked_work', app(BookLookup::class)->alternativeCovers('9780140328721')['status']);
        Http::assertSentCount(1);
    }

    public function test_disabled_lookup_does_not_contact_providers(): void
    {
        config(['book-lookup.enabled' => false]);
        $this->assertSame('disabled', app(BookLookup::class)->alternativeCovers('9780140328721')['status']);
        Http::assertNothingSent();
    }

    public function test_selected_alternative_provenance_is_saved_without_changing_isbn_or_stock(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn)->set('data.quantity', 7);
        $this->primeExact();
        $cover = app(BookLookup::class)->alternativeCovers($f['product']->isbn)['covers'][0];
        $page->call('chooseCover', $cover['key'], $page->get('lookupToken'))->assertHasNoErrors();
        $this->assertDatabaseCount('product_images', 0);
        $page->call('saveCatalogue')->assertHasNoErrors()->assertSet('data.quantity', 7);
        $this->assertDatabaseHas('product_images', ['product_id' => $f['product']->id, 'match_type' => 'alternative', 'edition_isbn' => '9780804429573']);
        $this->assertSame('9780140328721', $f['product']->fresh()->isbn);
        $this->assertDatabaseCount('receipt_lines', 0);
    }

    public function test_alternative_route_requires_staff(): void
    {
        $this->getJson('/intake/book-lookup/9780140328721?alternatives=1')->assertUnauthorized();
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        $this->primeExact();
        $this->getJson('/intake/book-lookup/9780140328721?alternatives=1')->assertOk()->assertJsonPath('covers.0.match_type', 'alternative');
    }

    public function test_provider_connection_failure_is_distinct_from_no_additional_images(): void
    {
        $exact = $this->primeExact();
        $this->failConnection = true;
        $this->assertSame('connection_failed', app(BookLookup::class)->alternativeCovers('9780140328721')['status']);
        $this->assertSame($exact, app(BookLookup::class)->lookup('9780140328721'));
        RateLimiter::clear('book-lookup:open-library');
        $this->failConnection = false;
        $this->entries = [];
        $this->assertSame('not_found', app(BookLookup::class)->alternativeCovers('9780140328721')['status']);
    }

    public function test_provider_rate_limit_is_distinct_from_a_malformed_response(): void
    {
        $this->primeExact();
        $this->status = 429;
        $this->assertSame('rate_limited', app(BookLookup::class)->alternativeCovers('9780140328721')['status']);
        RateLimiter::clear('book-lookup:open-library');
        $this->status = 200;
        $this->malformed = true;
        $this->assertSame('unavailable', app(BookLookup::class)->alternativeCovers('9780140328721')['status']);
    }
}
