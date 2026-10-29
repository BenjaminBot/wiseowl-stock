<?php

namespace App\Filament\Resources\IntakeSessions\Pages;

use App\Filament\Resources\IntakeSessions\IntakeSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIntakeSession extends EditRecord
{
    protected static string $resource = IntakeSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
