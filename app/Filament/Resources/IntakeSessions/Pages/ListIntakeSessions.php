<?php

namespace App\Filament\Resources\IntakeSessions\Pages;

use App\Filament\Resources\IntakeSessions\IntakeSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIntakeSessions extends ListRecords
{
    protected static string $resource = IntakeSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
