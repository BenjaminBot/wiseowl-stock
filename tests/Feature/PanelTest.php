<?php

namespace Tests\Feature;

use App\Filament\Pages\ReceiveDelivery;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    public function test_guests_are_sent_to_login_and_non_staff_are_denied(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_manager_can_render_every_foundation_screen(): void
    {
        $f = $this->inventoryFixture();
        foreach (['', 'products', 'products/create', 'categories', 'suppliers', 'locations', 'sales-channels', 'tax-categories', 'intake-sessions', 'intake-sessions/create', 'receive-delivery', 'stock-overview'] as $path) {
            $this->actingAs($f['staff'])->get('/admin'.($path ? '/'.$path : ''))->assertOk();
        }
    }

    public function test_clerk_can_receive_but_cannot_create_configuration(): void
    {
        $f = $this->inventoryFixture();
        $f['staff']->forceFill(['is_manager' => false])->save();
        $this->actingAs($f['staff'])->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertForbidden();
        $this->get('/admin/intake-sessions/create')->assertOk();
    }

    public function test_manual_receiving_page_posts_and_resets_item_fields(): void
    {
        $f = $this->inventoryFixture();
        $this->actingAs($f['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $input = $f['input'];
        unset($input['idempotency_key']);
        Livewire::test(ReceiveDelivery::class)->fillForm($input)->call('receive')->assertHasNoErrors()->assertSet('data.product_id', null)->assertSet('data.quantity', 1);
        $this->assertDatabaseCount('receipt_lines', 1);
    }
}
