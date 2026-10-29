<?php

namespace App\Filament\Resources\LookupProviders\Pages;

use App\Filament\Resources\LookupProviders\LookupProviderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLookupProvider extends EditRecord
{
    protected static string $resource = LookupProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
