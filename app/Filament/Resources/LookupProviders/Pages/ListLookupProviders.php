<?php

namespace App\Filament\Resources\LookupProviders\Pages;

use App\Filament\Resources\LookupProviders\LookupProviderResource;
use Filament\Resources\Pages\ListRecords;

class ListLookupProviders extends ListRecords
{
    protected static string $resource = LookupProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
