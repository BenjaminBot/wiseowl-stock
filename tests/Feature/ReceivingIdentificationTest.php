<?php

namespace Tests\Feature;

use App\Actions\Inventory\ReceiveStock;
use App\Filament\Pages\ReceiveDelivery;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class ReceivingIdentificationTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    private function receivingPage(array $fixture)
    {
        $this->actingAs($fixture['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::test(ReceiveDelivery::class)->fillForm(['intake_session_id' => $fixture['session']->id]);
    }

    public function test_isbn10_selects_existing_book_preserving_session_without_receiving_stock(): void
    {
        $f = $this->inventoryFixture();
        $this->receivingPage($f)->call('identifyIsbn', '0-14-032872-6')->assertHasNoErrors()
            ->assertSet('data.product_id', $f['product']->id)->assertSet('isbnInput', '9780140328721')
            ->assertSet('data.intake_session_id', $f['session']->id)->assertSet('data.price_confirmed', false);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('receipt_lines', 0);
        $this->assertDatabaseCount('stock_balances', 0);
    }

    public function test_repeated_scan_of_current_book_keeps_entered_quantity_and_price(): void
    {
        $f = $this->inventoryFixture();
        $page = $this->receivingPage($f)->call('identifyIsbn', $f['product']->isbn);
        $page->set('data.quantity', 7)->set('data.selling_price', '9.99')->set('data.price_confirmed', true)
            ->call('identifyIsbn', $f['product']->isbn)->assertSet('data.quantity', 7)
            ->assertSet('data.selling_price', '9.99')->assertSet('data.price_confirmed', true);
        $this->assertDatabaseCount('receipt_lines', 0);
    }

    public function test_unknown_and_invalid_isbns_keep_the_current_item_and_never_create_products(): void
    {
        $f = $this->inventoryFixture();
        $page = $this->receivingPage($f)->call('identifyIsbn', $f['product']->isbn)->set('data.quantity', 7);
        $page->call('identifyIsbn', '9780804429573')->assertSet('data.product_id', $f['product']->id)->assertSet('data.quantity', 7);
        $this->assertStringContainsString('No catalogue product', $page->get('scanMessage'));
        $page->call('identifyIsbn', '9780140328722')->assertHasErrors(['isbnInput'])
            ->assertSet('data.product_id', $f['product']->id)->assertSet('data.quantity', 7);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('receipt_lines', 0);
    }

    public function test_switching_books_requires_confirmation_and_resets_item_only(): void
    {
        $f = $this->inventoryFixture();
        $other = Product::create(['name' => 'Second book', 'sku' => 'SECOND', 'type' => 'book', 'isbn' => '9780804429573']);
        $page = $this->receivingPage($f)->call('identifyIsbn', $f['product']->isbn)->set('data.quantity', 7)->set('data.selling_price', '9.99')->set('data.price_confirmed', true);
        $page->call('identifyIsbn', $other->isbn)->assertSet('pendingProductId', $other->id)->assertSet('data.product_id', $f['product']->id)->assertSet('data.quantity', 7);
        $page->call('receive')->assertHasErrors(['isbnInput']);
        $page->call('cancelScannedProduct')->assertSet('pendingProductId', null)->assertSet('data.quantity', 7);
        $page->call('identifyIsbn', $other->isbn)->call('confirmScannedProduct')->assertHasNoErrors()
            ->assertSet('data.product_id', $other->id)->assertSet('data.quantity', 1)
            ->assertSet('data.selling_price', null)->assertSet('data.price_confirmed', false)
            ->assertSet('data.intake_session_id', $f['session']->id)->assertSet('pendingProductId', null);
        $this->assertDatabaseCount('receipt_lines', 0);
    }

    public function test_already_received_book_shows_count_without_adding_quantity(): void
    {
        $f = $this->inventoryFixture();
        app(ReceiveStock::class)->execute($f['staff'], $f['input']);
        $page = $this->receivingPage($f)->call('identifyIsbn', $f['product']->isbn)->assertSet('data.additional_quantity_confirmed', false);
        $this->assertStringContainsString('Already received 5 copies', $page->get('scanMessage'));
        $this->assertDatabaseCount('receipt_lines', 1);
    }

    public function test_lookup_is_authorised_even_when_invoked_directly(): void
    {
        $this->actingAs(User::factory()->create());
        $this->expectException(AuthorizationException::class);
        (new ReceiveDelivery)->identifyIsbn('9780140328721');
    }

    public function test_scanned_product_outside_initial_option_limit_has_a_selected_label(): void
    {
        $f = $this->inventoryFixture();
        $rows = [];
        for ($i = 0; $i < 201; $i++) {
            $rows[] = ['name' => 'A book '.$i, 'sku' => 'BULK-'.$i, 'type' => 'book'];
        }
        Product::insert($rows);
        $f['product']->update(['name' => 'ZZZ scanned book']);
        $this->receivingPage($f)->call('identifyIsbn', $f['product']->isbn)
            ->assertSet('data.product_id', $f['product']->id)->assertSee('ZZZ scanned book');
    }
}
