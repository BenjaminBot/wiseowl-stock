<?php

namespace Tests\Feature\Catalogue;

use App\Filament\Pages\ReceiveDelivery;
use App\Services\Books\BookLookup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class OpenLibraryAuthorsTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    private int $searchStatus = 200;

    private array $docs = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['book-lookup.enabled' => true, 'book-lookup.google_api_key' => 'fake-unused-key']);
        Http::preventStrayRequests();
        $this->docs = [['key' => '/works/OL99W', 'isbn' => ['9780140328721'], 'author_name' => ['Bjørn Lomborg', 'Author Two', 'Bjørn Lomborg']]];
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/search.json')) {
                return Http::response(['docs' => $this->docs], $this->searchStatus);
            }
            $edition = BookLookupTest::edition();
            $edition['authors'] = [['key' => '/authors/OL1A'], ['key' => '/authors/OL2A']];
            $edition['works'] = [['key' => '/works/OL99W']];

            return Http::response($edition);
        });
    }

    public function test_author_names_load_separately_are_cached_and_never_spend_google_quota(): void
    {
        $lookup = app(BookLookup::class);
        $initial = $lookup->lookup('9780140328721');
        $this->assertSame([], $initial['suggestions'][0]['authors']);
        $this->assertSame('pending', $initial['suggestions'][0]['author_status']);
        Http::assertSentCount(1);
        $this->assertSame('rate_limited', $lookup->authorDetails('9780140328721')['status']);
        RateLimiter::clear('book-lookup:open-library');
        $resolved = $lookup->authorDetails('9780140328721');
        $this->assertSame(['Bjørn Lomborg', 'Author Two'], $resolved['authors']);
        $this->assertSame($resolved['authors'], $lookup->lookup('9780140328721')['suggestions'][0]['authors']);
        $this->assertSame('found', $lookup->authorDetails('9780140328721')['status']);
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_applying_resolved_authors_requires_explicit_action_and_save(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn)
            ->set('catalogueData.author_names', ['Staff typed author']);
        $lookup = app(BookLookup::class);
        $initial = $lookup->lookup($f['product']->isbn);
        RateLimiter::clear('book-lookup:open-library');
        $lookup->authorDetails($f['product']->isbn);
        $page->assertSet('catalogueData.author_names', ['Staff typed author']);
        $this->assertDatabaseCount('book_details', 0);
        $page->call('useBookSuggestion', $initial['suggestions'][0]['key'], $page->get('lookupToken'))
            ->assertSet('catalogueData.author_names', ['Bjørn Lomborg', 'Author Two']);
        $this->assertDatabaseCount('book_details', 0);
        $page->call('saveCatalogue')->assertHasNoErrors();
        $this->assertSame(['Bjørn Lomborg', 'Author Two'], $f['product']->fresh()->bookDetail->author_names);
        $this->assertDatabaseCount('receipt_lines', 0);
    }

    public function test_author_failure_does_not_discard_metadata_or_covers(): void
    {
        $lookup = app(BookLookup::class);
        $initial = $lookup->lookup('9780140328721');
        RateLimiter::clear('book-lookup:open-library');
        $this->searchStatus = 503;
        $result = $lookup->authorDetails('9780140328721');
        $this->assertSame('unavailable', $result['status']);
        $after = $lookup->lookup('9780140328721');
        $this->assertSame($initial['covers'], $after['covers']);
        $this->assertSame($initial['suggestions'][0]['name'], $after['suggestions'][0]['name']);
    }

    public function test_unrelated_search_result_cannot_supply_author_names(): void
    {
        $lookup = app(BookLookup::class);
        $lookup->lookup('9780140328721');
        RateLimiter::clear('book-lookup:open-library');
        $this->docs = [['key' => '/works/OL1234W', 'isbn' => ['9780804429573'], 'author_name' => ['Wrong author']]];
        $this->assertSame([], $lookup->authorDetails('9780140328721')['authors']);
    }

    public function test_author_endpoint_requires_authenticated_staff(): void
    {
        $this->getJson('/intake/book-lookup/9780140328721?authors=1')->assertUnauthorized();
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        app(BookLookup::class)->lookup('9780140328721');
        RateLimiter::clear('book-lookup:open-library');
        $this->getJson('/intake/book-lookup/9780140328721?authors=1')->assertOk()->assertJsonPath('authors.0', 'Bjørn Lomborg');
    }
}
