<?php

namespace Tests\Feature\Catalogue;

use App\Actions\Catalogue\SaveIntakeBook;
use App\Filament\Pages\ReceiveDelivery;
use App\Models\Product;
use App\Services\Books\BookLookup;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class IntakeCatalogueTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    private ?array $edition = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['book-lookup.enabled' => true, 'book-lookup.google_api_key' => null]);
        Http::preventStrayRequests();
        Http::fake(['openlibrary.org/*' => fn () => Http::response($this->edition ?? BookLookupTest::edition())]);
    }

    private function page(array $fixture)
    {
        $this->actingAs($fixture['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::test(ReceiveDelivery::class);
    }

    public function test_existing_book_is_never_overwritten_by_lookup_and_applying_is_explicit(): void
    {
        $f = $this->inventoryFixture();
        $page = $this->page($f)->call('identifyIsbn', $f['product']->isbn)->assertSet('catalogueData.name', 'Test book');
        $result = app(BookLookup::class)->lookup($f['product']->isbn);
        $page->call('useBookSuggestion', $result['suggestions'][0]['key'], $page->get('lookupToken'))->assertHasNoErrors();
        $this->assertSame('Test book', $f['product']->fresh()->name);
        $page->call('saveCatalogue')->assertHasNoErrors();
        $this->assertSame('L-Għasfur iż-Żgħir', $f['product']->fresh()->name);
        $this->assertDatabaseCount('receipt_lines', 0);
    }

    public function test_unknown_book_can_be_created_with_manual_details_when_sources_fail(): void
    {
        $f = $this->inventoryFixture();
        $page = $this->page($f)->call('identifyIsbn', '9780804429573');
        $this->assertDatabaseCount('products', 1);
        $page->set('catalogueData.name', 'A provisional new book')->call('saveCatalogue')->assertHasNoErrors();
        $this->assertDatabaseHas('products', ['isbn' => '9780804429573', 'name' => 'A provisional new book']);
        $this->assertDatabaseHas('book_details', ['needs_review' => true]);
        $this->assertDatabaseCount('receipt_lines', 0);
        Http::assertNothingSent();
    }

    public function test_cover_candidates_are_explicit_and_do_not_change_receiving_draft(): void
    {
        $f = $this->inventoryFixture();
        $page = $this->page($f)->call('identifyIsbn', $f['product']->isbn)->set('data.quantity', 7);
        $result = app(BookLookup::class)->lookup($f['product']->isbn);
        $page->call('chooseCover', $result['covers'][1]['key'], $page->get('lookupToken'))->assertSet('coverChoice', 'candidate');
        $this->assertDatabaseCount('product_images', 0);
        $page->call('saveCatalogue')->assertHasNoErrors()->assertSet('data.quantity', 7);
        $this->assertDatabaseHas('product_images', ['product_id' => $f['product']->id, 'external_url' => $result['covers'][1]['url'], 'source' => 'Open Library']);
        $this->assertDatabaseCount('receipt_lines', 0);
    }

    public function test_stale_suggestion_token_cannot_change_another_isbn_draft(): void
    {
        $f = $this->inventoryFixture();
        $page = $this->page($f)->call('identifyIsbn', $f['product']->isbn);
        $token = $page->get('lookupToken');
        $result = app(BookLookup::class)->lookup($f['product']->isbn);
        $page->call('identifyIsbn', '9780804429573')->call('useBookSuggestion', $result['suggestions'][0]['key'], $token)->assertHasErrors(['catalogueData.name']);
        $this->assertSame('Test book', $f['product']->fresh()->name);
    }

    public function test_concurrent_catalogue_edit_is_detected_before_saving(): void
    {
        $f = $this->inventoryFixture();
        $page = $this->page($f)->call('identifyIsbn', $f['product']->isbn);
        $f['product']->update(['name' => 'Another staff edit']);
        $page->set('catalogueData.name', 'Stale edit')->call('saveCatalogue')->assertHasErrors(['catalogueData.name']);
        $this->assertSame('Another staff edit', $f['product']->fresh()->name);
    }

    public function test_same_isbn_created_elsewhere_is_reused_without_overwriting_it(): void
    {
        $f = $this->inventoryFixture();
        $page = $this->page($f)->call('identifyIsbn', '9780804429573')->set('catalogueData.name', 'My draft');
        Product::create(['isbn' => '9780804429573', 'name' => 'Already created by staff', 'sku' => 'OTHER', 'type' => 'book']);
        $page->call('saveCatalogue')->assertHasNoErrors();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('products', ['isbn' => '9780804429573', 'name' => 'Already created by staff']);
    }

    public function test_own_cover_upload_is_stored_and_selected(): void
    {
        Storage::fake('public');
        $f = $this->inventoryFixture();
        $page = $this->page($f)->call('identifyIsbn', $f['product']->isbn)
            ->fillForm(['cover_upload' => UploadedFile::fake()->image('cover.png')], 'catalogueForm')
            ->set('coverChoice', 'upload')->call('saveCatalogue')->assertHasNoErrors();
        $image = $f['product']->fresh()->coverImage;
        $this->assertSame('Own upload', $image->source);
        Storage::disk('public')->assertExists($image->storage_path);
    }

    public function test_clerk_cannot_save_catalogue_details_directly(): void
    {
        $f = $this->inventoryFixture();
        $f['staff']->forceFill(['is_manager' => false])->save();
        $this->expectException(AuthorizationException::class);
        app(SaveIntakeBook::class)->execute($f['staff'], $f['product']->isbn, $f['product']->id, 0, [], null, 'defer');
    }

    public function test_missing_provider_fields_do_not_erase_manually_entered_details(): void
    {
        $f = $this->inventoryFixture();
        $body = BookLookupTest::edition();
        $body['authors'] = [];
        unset($body['publishers']);
        $this->edition = $body;
        $page = $this->page($f)->call('identifyIsbn', $f['product']->isbn)
            ->set('catalogueData.author_names', ['Staff author'])->set('catalogueData.publisher', 'Staff publisher');
        $result = app(BookLookup::class)->lookup($f['product']->isbn);
        $page->call('useBookSuggestion', $result['suggestions'][0]['key'], $page->get('lookupToken'))
            ->assertSet('catalogueData.author_names', ['Staff author'])->assertSet('catalogueData.publisher', 'Staff publisher');
    }
}
