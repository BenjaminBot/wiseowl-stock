<?php

namespace App\Filament\Resources\IntakeSessions\Schemas;

use App\Filament\Resources\Locations\Schemas\LocationForm;
use App\Models\Location;
use App\Models\SalesChannel;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class IntakeSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reference')->required()->maxLength(255),
            Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload(),
            self::withLocationCreation(Select::make('receiving_location_id')->relationship('receivingLocation', 'name')->searchable()->preload()->required())->helperText('Where the books actually are when received. Allocation does not physically move stock.'),
            Select::make('default_condition')->options(['new' => 'New', 'almost_new' => 'Almost new', 'used' => 'Used'])->default('new')->required(),
            Toggle::make('website_separated')->label('Website stock physically separated')->default(true),
            Repeater::make('destinations')->schema([
                Select::make('sales_channel_id')->label('Sales destination')->options(fn () => SalesChannel::where('is_system', false)->pluck('name', 'id'))->required()->distinct(),
                self::withLocationCreation(Select::make('location_id')->label('Intended destination location')->options(fn () => Location::pluck('name', 'id'))->searchable()->required()),
            ])->minItems(1)->required()->columnSpanFull()->helperText('Select destinations once. Each receipt gets its own quantity split; unallocated stock is always permitted. Transfers to these locations are recorded separately.'),
        ]);
    }

    private static function withLocationCreation(Select $select): Select
    {
        return $select
            ->createOptionForm(LocationForm::fields(ignoreRecord: false))
            ->createOptionModalHeading('Create location')
            ->createOptionAction(fn (Action $action) => $action->authorize(fn () => Gate::allows('create', Location::class)))
            ->createOptionUsing(function (array $data): int {
                Gate::authorize('create', Location::class);
                $validated = Validator::make($data, ['name' => ['required', 'string', 'max:255', 'unique:locations,name']])->validate();

                return Location::create($validated)->getKey();
            });
    }
}
