<?php

namespace Tests\Feature;

use App\Filament\Pages\ReceiveDelivery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class ReceivingProductOptionsTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    public function test_product_dropdown_and_search_can_be_serialised_with_unicode_labels(): void
    {
        $fixture = $this->inventoryFixture();
        $fixture['product']->update(['name' => 'L-Għasfur iż-Żgħir']);
        $this->actingAs($fixture['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ReceiveDelivery::class)
            ->call('callSchemaComponentMethod', 'form.product_id', 'getOptionsForJs', [])
            ->assertHasNoErrors()
            ->call('callSchemaComponentMethod', 'form.product_id', 'getSearchResultsForJs', ['Għasfur'])
            ->assertHasNoErrors();
    }
}
