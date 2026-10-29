<?php

namespace App\Filament\Resources\SalesChannels;

use App\Filament\Resources\SalesChannels\Pages\CreateSalesChannel;
use App\Filament\Resources\SalesChannels\Pages\EditSalesChannel;
use App\Filament\Resources\SalesChannels\Pages\ListSalesChannels;
use App\Filament\Resources\SalesChannels\Schemas\SalesChannelForm;
use App\Filament\Resources\SalesChannels\Tables\SalesChannelsTable;
use App\Models\SalesChannel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalesChannelResource extends Resource
{
    protected static ?string $model = SalesChannel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SalesChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesChannelsTable::configure($table);
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
            'index' => ListSalesChannels::route('/'),
            'create' => CreateSalesChannel::route('/create'),
            'edit' => EditSalesChannel::route('/{record}/edit'),
        ];
    }
}
