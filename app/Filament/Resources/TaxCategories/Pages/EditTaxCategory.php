<?php

namespace App\Filament\Resources\TaxCategories\Pages;

use App\Filament\Resources\TaxCategories\TaxCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaxCategory extends EditRecord
{
    protected static string $resource = TaxCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
