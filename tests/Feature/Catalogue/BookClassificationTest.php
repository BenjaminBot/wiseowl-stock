<?php

namespace Tests\Feature\Catalogue;

use App\Actions\Catalogue\SaveIntakeBook;
use App\Filament\Pages\ReceiveDelivery;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Services\Books\BookLookup;
use App\Support\BookFormat;
use App\Support\BookLanguage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class BookClassificationTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    public function test_language_codes_names_and_regions_share_one_filter_value(): void
    {
        foreach (['en' => 'en', 'ENG' => 'en', 'English' => 'en', 'en-GB' => 'en',
            '/languages/eng' => 'en', 'fre' => 'fr', 'fra' => 'fr', 'French' => 'fr',
            'ger' => 'de', 'deu' => 'de', 'mlt' => 'mt', 'Maltese' => 'mt', 'zh-Hant' => 'zh'] as $input => $expected) {
            $this->assertSame($expected, BookLanguage::normalise($input), $input);
        }
        $this->assertSame('English', BookLanguage::options()['en']);
        $this->assertNull(BookLanguage::normalise('Made up language'));
        $this->assertNull(BookLanguage::normalise(null));
        $this->assertNotNull(BookLanguage::normalise('ace'));
    }

    public function test_binding_aliases_are_conservative_and_do_not_guess_generic_book_types(): void
    {
        foreach (['Hard Cover' => 'hardcover', 'hardback' => 'hardcover', 'Paperback' => 'paperback',
            'soft-cover' => 'paperback', 'mass market paperback' => 'paperback', 'Board book' => 'board_book',
            'spiral-bound' => 'spiral_bound', 'E-book' => 'ebook'] as $input => $expected) {
            $this->assertSame($expected, BookFormat::normalise($input));
        }
        $this->assertNull(BookFormat::normalise('BOOK'));
        $this->assertNull(BookFormat::normalise('Hardcover or paperback'));
    }

    public function test_lookup_apply_and_catalogue_save_use_canonical_dropdown_values(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['book-lookup.enabled' => true, 'book-lookup.google_api_key' => null]);
        Http::preventStrayRequests();
        Http::fake(['openlibrary.org/*' => Http::response([...BookLookupTest::edition(), 'languages' => [['key' => '/languages/eng']], 'physical_format' => 'Soft cover'])]);
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn);
        $result = app(BookLookup::class)->lookup($f['product']->isbn);
        $this->assertSame('en', $result['suggestions'][0]['language']);
        $page->call('useBookSuggestion', $result['suggestions'][0]['key'], $page->get('lookupToken'))
            ->assertSet('catalogueData.language', 'en')->assertSet('catalogueData.format', 'paperback')
            ->call('saveCatalogue')->assertHasNoErrors();
        $this->assertDatabaseHas('book_details', ['product_id' => $f['product']->id, 'language' => 'en', 'format' => 'paperback']);
        $this->assertDatabaseCount('receipt_lines', 0);
    }

    public function test_unmatched_lookup_values_remain_visible_without_blocking_catalogue_save(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['book-lookup.enabled' => true, 'book-lookup.google_api_key' => null]);
        Http::preventStrayRequests();
        Http::fake(['openlibrary.org/*' => Http::response([...BookLookupTest::edition(), 'languages' => [['key' => '/languages/unknown']], 'physical_format' => 'Special publisher binding'])]);
        $page = Livewire::test(ReceiveDelivery::class)->call('identifyIsbn', $f['product']->isbn);
        $result = app(BookLookup::class)->lookup($f['product']->isbn);
        $page->call('useBookSuggestion', $result['suggestions'][0]['key'], $page->get('lookupToken'))
            ->assertSet('catalogueData.language', null)->assertSet('catalogueData.language_original', 'unknown')
            ->assertSet('catalogueData.format_original', 'Special publisher binding')->assertSee('Provider / previous format')->call('saveCatalogue')->assertHasNoErrors();
        $this->assertDatabaseHas('book_details', ['product_id' => $f['product']->id, 'language' => null, 'language_original' => 'unknown', 'format_original' => 'Special publisher binding']);
    }

    public function test_action_rejects_non_list_values_from_direct_requests(): void
    {
        $f = $this->inventoryFixture();
        $this->expectException(ValidationException::class);
        app(SaveIntakeBook::class)->execute($f['staff'], $f['product']->isbn, $f['product']->id, $f['product']->catalogue_version,
            ['name' => 'Book', 'needs_review' => true, 'language' => 'invented', 'format' => 'invented'], null, 'defer');
    }

    public function test_product_filters_and_columns_use_the_same_canonical_values(): void
    {
        $f = $this->inventoryFixture();
        $f['product']->bookDetail()->create(['language' => 'eng', 'format' => 'Hard cover', 'updated_by' => $f['staff']->id]);
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$f['product']])
            ->filterTable('language', 'en')->assertCanSeeTableRecords([$f['product']])
            ->filterTable('format', 'paperback')->assertCanNotSeeTableRecords([$f['product']])
            ->filterTable('format', 'hardcover')->assertCanSeeTableRecords([$f['product']])
            ->sortTable('bookDetail.language')->assertHasNoErrors();
    }

    public function test_migration_normalises_existing_rows_and_preserves_unmatched_originals(): void
    {
        $f = $this->inventoryFixture();
        $migration = require database_path('migrations/2026_10_29_181000_normalise_book_language_and_format.php');
        $migration->down();
        DB::table('book_details')->insert(['product_id' => $f['product']->id, 'language' => 'eng', 'format' => 'Unusual binding', 'needs_review' => false, 'updated_by' => $f['staff']->id]);
        $migration->up();
        $this->assertDatabaseHas('book_details', ['language' => 'en', 'language_original' => 'eng', 'format' => null, 'format_original' => 'Unusual binding', 'needs_review' => true]);
        $this->assertSame(1, $f['product']->fresh()->catalogue_version);
    }
}
