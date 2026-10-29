<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Models\Location;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::fields());
    }

    public static function fields(bool $ignoreRecord = true): array
    {
        return [TextInput::make('name')->required()->maxLength(255)->unique(table: Location::class, column: 'name', ignoreRecord: $ignoreRecord)];
    }
}
