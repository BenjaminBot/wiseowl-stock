<?php

namespace App\Filament\Resources\IntakeSessions\Pages;

use App\Filament\Resources\IntakeSessions\IntakeSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIntakeSession extends CreateRecord
{
    protected static string $resource = IntakeSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = 'open';
        $data['currency'] = 'EUR';

        return $data;
    }
}
