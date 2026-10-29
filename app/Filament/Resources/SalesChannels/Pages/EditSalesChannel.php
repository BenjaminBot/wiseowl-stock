<?php

namespace App\Filament\Resources\SalesChannels\Pages;

use App\Filament\Resources\SalesChannels\SalesChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesChannel extends EditRecord
{
    protected static string $resource = SalesChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
