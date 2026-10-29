<?php

namespace Tests\Feature;

use App\Filament\Resources\IntakeSessions\Pages\CreateIntakeSession;
use App\Models\Location;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class InlineLocationTest extends TestCase
{
    use BuildsInventory, RefreshDatabase;

    public function test_receiving_location_is_created_selected_and_keeps_unsaved_session_input(): void
    {
        $fixture = $this->inventoryFixture();
        $this->actingAs($fixture['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(CreateIntakeSession::class)
            ->fillForm(['reference' => 'Unsaved delivery'])
            ->callAction(TestAction::make('createOption')->schemaComponent('receiving_location_id'), data: ['name' => 'New stockroom'])
            ->assertHasNoActionErrors();
        $location = Location::where('name', 'New stockroom')->sole();
        $page->assertSet('data.receiving_location_id', $location->id)->assertSet('data.reference', 'Unsaved delivery');
        $this->assertDatabaseCount('intake_sessions', 1);
    }

    public function test_destination_location_can_be_created_inside_repeater(): void
    {
        $fixture = $this->inventoryFixture();
        $this->actingAs($fixture['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(CreateIntakeSession::class)->fillForm([
            'reference' => 'Fair delivery',
            'destinations' => [['sales_channel_id' => $fixture['fair']->id, 'location_id' => null]],
        ]);
        $row = array_key_first($page->get('data.destinations'));
        $page->callAction(TestAction::make('createOption')->schemaComponent('destinations.'.$row.'.location_id'), data: ['name' => 'New fair box'])
            ->assertHasNoActionErrors();
        $location = Location::where('name', 'New fair box')->sole();
        $page->assertSet('data.destinations.'.$row.'.location_id', $location->id)
            ->assertSet('data.destinations.'.$row.'.sales_channel_id', $fixture['fair']->id);
    }

    public function test_duplicate_location_name_is_validated_in_modal(): void
    {
        $fixture = $this->inventoryFixture();
        $this->actingAs($fixture['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(CreateIntakeSession::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('receiving_location_id'), data: ['name' => $fixture['location']->name])
            ->assertHasActionErrors(['name' => 'unique']);
        $this->assertDatabaseCount('locations', 2);
    }

    public function test_clerk_cannot_mount_the_location_creation_action(): void
    {
        $fixture = $this->inventoryFixture();
        $fixture['staff']->forceFill(['is_manager' => false])->save();
        $this->actingAs($fixture['staff']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(CreateIntakeSession::class)
            ->assertActionHidden(TestAction::make('createOption')->schemaComponent('receiving_location_id'))
            ->call('mountAction', 'createOption', [], ['schemaComponent' => 'form.receiving_location_id'])
            ->assertSet('mountedActions', []);
        $this->assertDatabaseCount('locations', 2);
    }
}
