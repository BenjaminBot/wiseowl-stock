<?php

namespace App\Filament\Resources\TaxCategories\Pages;

use App\Filament\Resources\TaxCategories\TaxCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxCategory extends CreateRecord
{
    protected static string $resource = TaxCategoryResource::class;
}
