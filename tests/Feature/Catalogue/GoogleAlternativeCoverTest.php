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

class GoogleAlternativeCoverTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    private bool $failOpenLibrary = false;

    private bool $resolveAuthor = false;

    private int $googleStatus = 200;

    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['book-lookup.enabled' => true, 'book-lookup.google_api_key' => 'fake-google-key']);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/authors/OL1A.json')) {
                return Http::response(['name' => 'Bjørn Lomborg']);
            }
            if (str_contains($request->url(), 'www.googleapis.com')) {
                return str_starts_with($request['q'], 'isbn:') ? Http::response(['totalItems' => 0])
                    : Http::response(['items' => $this->items], $this->googleStatus);
            }
            if (str_contains($request->url(), '/works/')) {
                return Http::response(['entries' => []], $this->failOpenLibrary ? 503 : 200);
            }
            $edition = BookLookupTest::edition();
            $edition['title'] = 'False Alarm';
            $edition['authors'] = $this->resolveAuthor ? [['key' => '/authors/OL1A']] : [['name' => 'Bjorn Lomborg']];
            $edition['works'] = [['key' => '/works/OL99W']];

            return Http::response($edition);
        });
        $this->items = [$this->volume()];
    }

    private function volume(): array
    {
        return ['id' => 'other_edition', 'volumeInfo' => ['title' => 'False Alarm', 'authors' => ['Bjorn Lomborg'],
            'publisher' => 'Basic Books', 'publishedDate' => '2020', 'industryIdentifiers' => [['identifier' => '9781541647626']],
            'imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=other_edition&key=remove-me']]];
    }

    private function prime(): void
    {
        app(BookLookup::class)->lookup('9780140328721', true);
        RateLimiter::clear('book-lookup:open-library');
    }

    public function test_title_author_cover_is_labelled_as_alternative_and_separate_from_exact_matches(): void
    {
        $wrong = $this->volume();
        $wrong['id'] = 'wrong';
        $wrong['volumeInfo']['title'] = 'Unrelated book';
        $exact = $this->volume();
        $exact['id'] = 'same_isbn';
        $exact['volumeInfo']['industryIdentifiers'] = [['identifier' => '9780140328721']];
        $this->items = [$this->volume(), $wrong, $exact];
        $this->prime();
        $result = app(BookLookup::class)->alternativeCovers('9780140328721', true);
        $this->assertCount(1, $result['covers']);
        $cover = $result['covers'][0];
        $this->assertSame('Google Books', $cover['source']);
        $this->assertSame('alternative', $cover['match_type']);
        $this->assertSame('title', $cover['match_basis']);
        $this->assertSame('9781541647626', $cover['edition_isbn']);
        $this->assertStringNotContainsString('remove-me', $cover['url']);
        $this->assertSame(['Open Library' => 'not_found', 'Google Books' => 'found'], $result['sources']);
        $this->assertSame(2, count(app(BookLookup::class)->lookup('9780140328721')['covers']));
        $again = app(BookLookup::class)->alternativeCovers('9780140328721', true);
        $this->assertSame($result, $again);
        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'googleapis.com') && $request['q'] === 'False Alarm Bjorn Lomborg');
    }

    public function test_one_failed_provider_does_not_hide_google_covers(): void
    {
        $this->failOpenLibrary = true;
        $this->prime();
        $result = app(BookLookup::class)->alternativeCovers('9780140328721', true);
        $this->assertSame('found', $result['status']);
        $this->assertTrue($result['partial_failure']);
        $this->assertCount(1, $result['covers']);
    }

    public function test_author_resolution_is_cached_and_respects_shared_provider_limit(): void
    {
        $this->resolveAuthor = true;
        $this->prime();
        $this->assertSame('rate_limited', app(BookLookup::class)->alternativeCovers('9780140328721', true)['status']);
        RateLimiter::clear('book-lookup:open-library');
        $this->assertSame('found', app(BookLookup::class)->alternativeCovers('9780140328721', true)['status']);
        $this->assertSame('found', app(BookLookup::class)->alternativeCovers('9780140328721', true)['status']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/authors/OL1A.json'));
        Http::assertSentCount(5);
    }

    public function test_google_auth_error_is_not_reported_as_no_covers(): void
    {
        $this->googleStatus = 403;
        $this->prime();
        $result = app(BookLookup::class)->alternativeCovers('9780140328721', true);
        $this->assertSame('configuration_error', $result['status']);
        $this->assertStringNotContainsString('fake-google-key', json_encode($result));
    }

    public function test_selecting_google_alternative_preserves_isbn_and_only_saves_on_confirmation(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn)->set('data.quantity', 5);
        $this->prime();
        $cover = app(BookLookup::class)->alternativeCovers($f['product']->isbn, true)['covers'][0];
        $page->call('chooseCover', $cover['key'], $page->get('lookupToken'))->assertHasNoErrors();
        $this->assertDatabaseCount('product_images', 0);
        $page->call('saveCatalogue')->assertHasNoErrors()->assertSet('data.quantity', 5);
        $this->assertDatabaseHas('product_images', ['product_id' => $f['product']->id, 'source' => 'Google Books', 'match_type' => 'alternative', 'edition_isbn' => '9781541647626']);
        $this->assertSame('9780140328721', $f['product']->fresh()->isbn);
        $this->assertDatabaseCount('receipt_lines', 0);
        Http::assertSentCount(4);
    }

    public function test_google_is_never_called_without_explicit_opt_in_even_with_a_key(): void
    {
        $default = app(BookLookup::class)->lookup('9780140328721');
        RateLimiter::clear('book-lookup:open-library');
        app(BookLookup::class)->alternativeCovers('9780140328721');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
        $this->assertArrayNotHasKey('Google Books', $default['sources']);
        $enabled = app(BookLookup::class)->lookup('9780140328721', true);
        $this->assertArrayHasKey('Google Books', $enabled['sources']);
        $this->assertSame($default, app(BookLookup::class)->lookup('9780140328721'));
    }

    public function test_route_requires_google_flag_to_query_google(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        $this->getJson('/intake/book-lookup/9780140328721')->assertOk();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
        $this->getJson('/intake/book-lookup/9780140328721?google=1')->assertOk()->assertJsonPath('sources.Google Books', 'not_found');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }
}
