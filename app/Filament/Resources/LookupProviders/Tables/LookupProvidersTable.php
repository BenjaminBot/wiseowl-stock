<?php

namespace App\Filament\Resources\LookupProviders\Tables;

use App\Models\LookupProvider;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LookupProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('kind')->state(fn (LookupProvider $record) => LookupProvider::DEFINITIONS[$record->key]['kind']),
            TextColumn::make('metadata_mode')->label('Book information')->badge(),
            TextColumn::make('image_mode')->label('Cover / images')->badge(),
            TextColumn::make('credential_status')->label('Credentials'),
            TextColumn::make('updated_at')->dateTime()->label('Last changed'),
        ])->recordActions([EditAction::make()]);
    }
}
