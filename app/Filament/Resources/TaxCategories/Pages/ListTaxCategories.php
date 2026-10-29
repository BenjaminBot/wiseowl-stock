<?php

namespace App\Filament\Resources\TaxCategories\Pages;

use App\Filament\Resources\TaxCategories\TaxCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaxCategories extends ListRecords
{
    protected static string $resource = TaxCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
