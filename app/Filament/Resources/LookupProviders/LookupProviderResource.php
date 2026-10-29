<?php

namespace App\Filament\Resources\LookupProviders;

use App\Filament\Resources\LookupProviders\Pages\EditLookupProvider;
use App\Filament\Resources\LookupProviders\Pages\ListLookupProviders;
use App\Filament\Resources\LookupProviders\Schemas\LookupProviderForm;
use App\Filament\Resources\LookupProviders\Tables\LookupProvidersTable;
use App\Models\LookupProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LookupProviderResource extends Resource
{
    protected static ?string $model = LookupProvider::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LookupProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LookupProvidersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLookupProviders::route('/'),
            'edit' => EditLookupProvider::route('/{record}/edit'),
        ];
    }
}
