<?php

namespace App\Filament\Resources\TaxCategories;

use App\Filament\Resources\TaxCategories\Pages\CreateTaxCategory;
use App\Filament\Resources\TaxCategories\Pages\EditTaxCategory;
use App\Filament\Resources\TaxCategories\Pages\ListTaxCategories;
use App\Filament\Resources\TaxCategories\Schemas\TaxCategoryForm;
use App\Filament\Resources\TaxCategories\Tables\TaxCategoriesTable;
use App\Models\TaxCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TaxCategoryResource extends Resource
{
    protected static ?string $model = TaxCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TaxCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxCategoriesTable::configure($table);
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
            'index' => ListTaxCategories::route('/'),
            'create' => CreateTaxCategory::route('/create'),
            'edit' => EditTaxCategory::route('/{record}/edit'),
        ];
    }
}
