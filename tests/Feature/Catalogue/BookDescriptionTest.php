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

class BookDescriptionTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    private array $work = ['key' => '/works/OL99W', 'description' => ['value' => '<p>A book about testing.</p><p>Second paragraph.</p><script>bad()</script>']];

    protected function setUp(): void
    {
        parent::setUp();
        config(['book-lookup.enabled' => true, 'book-lookup.google_api_key' => 'fake-unused-key']);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/works/OL99W.json')) {
                return Http::response($this->work);
            }
            if (str_contains($request->url(), 'googleapis.com')) {
                return Http::response(['items' => [['id' => 'exact_volume', 'volumeInfo' => ['title' => 'Matching book',
                    'industryIdentifiers' => [['identifier' => '9780140328721']], 'description' => '<p>Google description.</p><style>hidden</style>']]]]);
            }
            $edition = BookLookupTest::edition();
            $edition['works'] = [['key' => '/works/OL99W']];

            return Http::response($edition);
        });
    }

    private function resolve(): array
    {
        $lookup = app(BookLookup::class);
        $lookup->lookup('9780140328721');
        RateLimiter::clear('book-lookup:open-library');

        return $lookup->descriptionDetails('9780140328721');
    }

    public function test_work_description_is_cleaned_cached_and_does_not_use_google(): void
    {
        $r = $this->resolve();
        $this->assertSame('found', $r['status']);
        $this->assertSame("A book about testing.\nSecond paragraph.", $r['description']);
        $this->assertSame($r['description'], app(BookLookup::class)->lookup('9780140328721')['suggestions'][0]['description']);
        $this->assertSame($r, app(BookLookup::class)->descriptionDetails('9780140328721'));
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_description_load_does_not_overwrite_staff_draft_and_requires_explicit_apply_and_save(): void
    {
        $f = $this->inventoryFixture();
        $f['product']->update(['description' => 'Existing staff description']);
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn)
            ->set('catalogueData.description', 'Unsaved staff writing')->set('data.quantity', 8);
        $r = $this->resolve();
        $page->assertSet('catalogueData.description', 'Unsaved staff writing');
        $this->assertSame('Existing staff description', $f['product']->fresh()->description);
        $page->call('useBookSuggestion', $r['suggestions'][0]['key'], $page->get('lookupToken'))
            ->assertSet('catalogueData.description', $r['description']);
        $this->assertSame('Existing staff description', $f['product']->fresh()->description);
        $page->call('saveCatalogue')->assertHasNoErrors()->assertSet('data.quantity', 8);
        $this->assertSame($r['description'], $f['product']->fresh()->description);
        $this->assertDatabaseCount('receipt_lines', 0);
    }

    public function test_missing_description_keeps_staff_text_when_details_are_applied(): void
    {
        unset($this->work['description']);
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn)
            ->set('catalogueData.description', 'Keep my writing');
        $r = $this->resolve();
        $this->assertSame('not_found', $r['status']);
        $page->call('useBookSuggestion', $r['suggestions'][0]['key'], $page->get('lookupToken'))->assertSet('catalogueData.description', 'Keep my writing');
    }

    public function test_wrong_work_cannot_supply_a_description(): void
    {
        $this->work['key'] = '/works/OL100W';
        $r = $this->resolve();
        $this->assertSame('unavailable', $r['status']);
        $this->assertNull($r['description']);
    }

    public function test_google_description_only_joins_after_opt_in(): void
    {
        $default = app(BookLookup::class)->lookup('9780140328721');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
        $enabled = app(BookLookup::class)->lookup('9780140328721', true);
        $google = collect($enabled['suggestions'])->firstWhere('source', 'Google Books');
        $this->assertSame('Google description.', $google['description']);
        $this->assertNull($default['suggestions'][0]['description']);
    }

    public function test_details_can_be_applied_while_description_is_still_pending(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn)
            ->set('catalogueData.description', 'Keep staff draft');
        $initial = app(BookLookup::class)->lookup($f['product']->isbn);
        $this->assertSame('pending', $initial['suggestions'][0]['description_status']);
        $page->call('useBookSuggestion', $initial['suggestions'][0]['key'], $page->get('lookupToken'))
            ->assertHasNoErrors()->assertSet('catalogueData.description', 'Keep staff draft')
            ->assertSet('catalogueEditorExpanded', true)->assertSet('catalogueData.name', 'L-Għasfur iż-Żgħir');
        $this->assertSame('Test book', $f['product']->fresh()->name);
        Http::assertSentCount(1);
    }
}
