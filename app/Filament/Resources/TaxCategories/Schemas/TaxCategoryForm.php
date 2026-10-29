<?php

namespace App\Filament\Resources\TaxCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaxCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('rate')->label('Tax rate (%)')->numeric()->minValue(0)->maxValue(100)->step('0.0001')->required()->helperText('Configure confirmed applicable rates; no legal tax rates are supplied automatically.'),
        ]);
    }
}
