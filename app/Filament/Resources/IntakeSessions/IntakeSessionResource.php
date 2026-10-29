<?php

namespace App\Filament\Resources\IntakeSessions;

use App\Filament\Resources\IntakeSessions\Pages\CreateIntakeSession;
use App\Filament\Resources\IntakeSessions\Pages\EditIntakeSession;
use App\Filament\Resources\IntakeSessions\Pages\ListIntakeSessions;
use App\Filament\Resources\IntakeSessions\Schemas\IntakeSessionForm;
use App\Filament\Resources\IntakeSessions\Tables\IntakeSessionsTable;
use App\Models\IntakeSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IntakeSessionResource extends Resource
{
    protected static ?string $model = IntakeSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Schema $schema): Schema
    {
        return IntakeSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IntakeSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntakeSessions::route('/'),
            'create' => CreateIntakeSession::route('/create'),
            'edit' => EditIntakeSession::route('/{record}/edit'),
        ];
    }
}
