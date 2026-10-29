<?php

namespace App\Filament\Resources\LookupProviders\Schemas;

use App\Models\LookupProvider;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LookupProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        $modes = ['automatic' => 'Automatic', 'manual' => 'Manual / on request', 'disabled' => 'Disabled'];

        return $schema->components([
            TextInput::make('name')->disabled()->dehydrated(false),
            TextInput::make('credential_status')->label('Credentials')->formatStateUsing(fn (?LookupProvider $record) => $record?->credential_status)
                ->disabled()->dehydrated(false)->helperText('API keys stay in the local environment configuration; this page never displays them.'),
            Select::make('metadata_mode')->label('Book information')->options($modes)->required()
                ->disabled(fn (?LookupProvider $record) => $record?->key === 'brave')->helperText('Title, authors, description and publishing details.'),
            Select::make('image_mode')->label('Cover / image lookup')->options($modes)->required()
                ->helperText('Automatic runs after scanning. Manual requires a provider button. Disabled prevents calls and hides cached results.'),
            Hidden::make('updated_by')->dehydrateStateUsing(fn () => auth()->id()),
        ]);
    }
}
