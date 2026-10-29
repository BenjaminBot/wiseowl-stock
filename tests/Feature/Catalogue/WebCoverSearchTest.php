<?php

namespace Tests\Feature\Catalogue;

use App\Filament\Pages\ReceiveDelivery;
use App\Models\User;
use App\Services\Books\CoverUrl;
use App\Services\Books\WebCoverSearch;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class WebCoverSearchTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['book-lookup.enabled' => true, 'book-lookup.brave_api_key' => 'fake-brave-key']);
        Http::preventStrayRequests();
    }

    private function item(int $id = 1): array
    {
        return ['title' => 'False Alarm cover '.$id, 'url' => 'https://publisher.com/books/'.$id,
            'thumbnail' => ['src' => 'https://imgs.search.brave.com/thumbnail'.$id],
            'properties' => ['url' => 'https://images.publisher.com/cover'.$id.'.jpg']];
    }

    public function test_unconfigured_and_disabled_providers_do_not_make_requests(): void
    {
        config(['book-lookup.brave_api_key' => null]);
        $this->assertSame('not_configured', app(WebCoverSearch::class)->search('9780140328721')['status']);
        config(['book-lookup.enabled' => false]);
        $this->assertSame('disabled', app(WebCoverSearch::class)->search('9780140328721')['status']);
        Http::assertNothingSent();
    }

    public function test_web_candidates_are_labelled_deduplicated_bounded_and_cached(): void
    {
        $items = array_map(fn ($id) => $this->item($id), range(1, 18));
        array_unshift($items, $this->item(1));
        Http::fake(['api.search.brave.com/*' => Http::response(['results' => $items])]);
        $result = app(WebCoverSearch::class)->search('0140328726');
        $this->assertCount(12, $result['covers']);
        $this->assertSame('web', $result['covers'][0]['match_type']);
        $this->assertNull($result['covers'][0]['edition_isbn']);
        $this->assertSame('publisher.com', $result['covers'][0]['source_host']);
        $this->assertSame($result, app(WebCoverSearch::class)->search('9780140328721'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Subscription-Token', 'fake-brave-key')
            && $request['q'] === '9780140328721 book cover' && $request['spellcheck'] === 'false');
        $this->assertStringNotContainsString('fake-brave-key', json_encode($result));
    }

    public function test_unsafe_urls_and_non_provider_thumbnails_are_excluded(): void
    {
        $badImage = $this->item(1);
        $badImage['properties']['url'] = 'https://127.0.0.1/private';
        $badSource = $this->item(2);
        $badSource['url'] = 'javascript:alert(1)';
        $badThumbnail = $this->item(3);
        $badThumbnail['thumbnail']['src'] = 'https://other-domain.com/tracker';
        Http::fake(['api.search.brave.com/*' => Http::response(['results' => [$badImage, $badSource, $badThumbnail, $this->item(4)]])]);
        $result = app(WebCoverSearch::class)->search('9780140328721');
        $this->assertCount(1, $result['covers']);
        $this->assertSame('https://images.publisher.com/cover4.jpg', $result['covers'][0]['url']);
    }

    public function test_public_url_validation_rejects_local_addresses_and_strips_credentials(): void
    {
        foreach (['http://publisher.com/x', 'https://localhost/x', 'https://192.168.0.1/x', 'https://[::1]/x',
            'https://localhost./x', 'https://127.0.0.1./x', 'https://127.000.000.001/x', 'https://0x7f.1/x', 'https://bookshop.local./x', 'https://bookshop.local/x', 'https://user:pass@publisher.com/x', 'https://publisher.com:8000/x',
            'https://publisher.com/with space', 'https://publisher.com\\x'] as $url) {
            $this->assertNull(CoverUrl::publicHttps($url), $url);
        }
        $this->assertSame('https://publisher.com/x?size=large', CoverUrl::publicHttps('https://publisher.com/x?API_KEY=secret&access_token=secret&size=large'));
    }

    public function test_provider_errors_are_specific_and_do_not_leak_credentials(): void
    {
        Http::fake(['api.search.brave.com/*' => Http::sequence()->push([], 401)->push([], 429)->push([], 503)->push(['bad' => 'body'])]);
        foreach (['configuration_error', 'rate_limited', 'unavailable', 'unavailable'] as $expected) {
            RateLimiter::clear('book-lookup:brave');
            $result = app(WebCoverSearch::class)->search('9780140328721');
            $this->assertSame($expected, $result['status']);
            $this->assertStringNotContainsString('fake-brave-key', json_encode($result));
        }
    }

    public function test_staff_route_and_explicit_selection_preserve_isbn_metadata_and_receiving_draft(): void
    {
        Http::fake(['api.search.brave.com/*' => Http::response(['results' => [$this->item()]])]);
        $this->getJson('/intake/book-lookup/9780140328721?web=1')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/intake/book-lookup/9780140328721?web=1')->assertForbidden();
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->getJson('/intake/book-lookup/9780140328721?web=1')->assertOk()->assertJsonPath('covers.0.match_type', 'web');
        $cover = app(WebCoverSearch::class)->search($f['product']->isbn)['covers'][0];
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn)->set('data.quantity', 7);
        $page->call('chooseCover', $cover['key'], $page->get('lookupToken'))->assertHasNoErrors();
        $this->assertDatabaseCount('product_images', 0);
        $page->call('saveCatalogue')->assertHasNoErrors()->assertSet('data.quantity', 7);
        $this->assertDatabaseHas('product_images', ['product_id' => $f['product']->id, 'source' => 'Brave Image Search', 'match_type' => 'web', 'source_url' => $cover['source_url']]);
        $this->assertSame('9780140328721', $f['product']->fresh()->isbn);
        $this->assertSame('Test book', $f['product']->fresh()->name);
        $this->assertDatabaseCount('receipt_lines', 0);
        Http::assertSentCount(1);
    }

    public function test_unknown_cached_candidate_is_rejected_without_an_extra_request(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn);
        $page->call('chooseCover', 'web:forged', $page->get('lookupToken'))->assertHasErrors('coverChoice');
        Http::assertNothingSent();
    }
}
