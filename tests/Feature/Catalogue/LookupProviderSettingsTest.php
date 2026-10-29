<?php

namespace Tests\Feature\Catalogue;

use App\Filament\Resources\LookupProviders\Pages\EditLookupProvider;
use App\Models\LookupProvider;
use App\Services\Books\BookLookup;
use App\Services\Books\WebCoverSearch;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class LookupProviderSettingsTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['book-lookup.enabled' => true, 'book-lookup.google_api_key' => 'fake-key', 'book-lookup.brave_api_key' => 'fake-brave']);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'googleapis.com')) {
                return Http::response(['items' => [['id' => 'google', 'volumeInfo' => [
                    'title' => 'Google title', 'industryIdentifiers' => [['identifier' => '9780140328721']],
                    'imageLinks' => ['thumbnail' => 'https://books.google.com/books/content?id=google']]]]]);
            }

            return Http::response(BookLookupTest::edition());
        });
    }

    public function test_defaults_preserve_open_library_automatic_and_other_providers_manual(): void
    {
        $this->assertDatabaseHas('lookup_providers', ['key' => 'open_library', 'metadata_mode' => 'automatic', 'image_mode' => 'automatic']);
        $this->assertDatabaseHas('lookup_providers', ['key' => 'google_books', 'metadata_mode' => 'manual', 'image_mode' => 'manual']);
        app(BookLookup::class)->lookup('9780140328721');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_disabled_provider_prevents_requests_and_cached_results_are_not_reused(): void
    {
        $lookup = app(BookLookup::class);
        $this->assertNotEmpty($lookup->lookup('9780140328721')['suggestions']);
        LookupProvider::where('key', 'open_library')->firstOrFail()->update(['metadata_mode' => 'disabled', 'image_mode' => 'disabled']);
        $result = $lookup->lookup('9780140328721', false, true);
        $this->assertSame([], $result['suggestions']);
        $this->assertSame([], $result['covers']);
        Http::assertSentCount(1);
    }

    public function test_information_and_images_can_be_controlled_independently(): void
    {
        LookupProvider::where('key', 'open_library')->firstOrFail()->update(['metadata_mode' => 'automatic', 'image_mode' => 'manual']);
        $lookup = app(BookLookup::class);
        $normal = $lookup->lookup('9780140328721');
        $this->assertNotEmpty($normal['suggestions']);
        $this->assertSame([], $normal['covers']);
        $manual = $lookup->lookup('9780140328721', false, true);
        $this->assertNotEmpty($manual['covers']);
        Http::assertSentCount(1);
    }

    public function test_google_automatic_mode_is_explicitly_configurable_and_metadata_can_stay_disabled(): void
    {
        LookupProvider::where('key', 'google_books')->firstOrFail()->update(['metadata_mode' => 'disabled', 'image_mode' => 'automatic']);
        $result = app(BookLookup::class)->lookup('9780140328721');
        $this->assertSame(['Open Library'], collect($result['suggestions'])->pluck('source')->unique()->values()->all());
        $this->assertContains('Google Books', array_column($result['covers'], 'source'));
        Http::assertSentCount(2);
    }

    public function test_brave_disabled_blocks_direct_and_automatic_requests(): void
    {
        LookupProvider::where('key', 'brave')->firstOrFail()->update(['image_mode' => 'disabled']);
        $this->assertSame('disabled', app(WebCoverSearch::class)->search('9780140328721')['status']);
        $this->assertSame('disabled', app(WebCoverSearch::class)->search('9780140328721', false)['status']);
        Http::assertNothingSent();
    }

    public function test_provider_configuration_requires_manager_and_records_actor(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $record = LookupProvider::where('key', 'google_books')->firstOrFail();
        $this->get('/admin/lookup-providers')->assertOk();
        Livewire::test(EditLookupProvider::class, ['record' => $record->id])
            ->fillForm(['metadata_mode' => 'disabled', 'image_mode' => 'manual'])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseHas('lookup_providers', ['id' => $record->id, 'metadata_mode' => 'disabled', 'updated_by' => $f['staff']->id]);
        $f['staff']->forceFill(['is_manager' => false])->save();
        $this->assertFalse(Gate::forUser($f['staff'])->allows('update', $record));
        $this->get('/admin/lookup-providers')->assertForbidden();
    }

    public function test_unsupported_metadata_capability_cannot_be_enabled(): void
    {
        $this->expectException(ValidationException::class);
        LookupProvider::where('key', 'brave')->firstOrFail()->update(['metadata_mode' => 'automatic']);
    }
}
