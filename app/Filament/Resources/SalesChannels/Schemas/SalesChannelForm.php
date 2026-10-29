<?php

namespace App\Filament\Resources\SalesChannels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SalesChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('type')->options(['website' => 'Website', 'shop' => 'Shop', 'bookfair' => 'Bookfair', 'other' => 'Other'])->required()->default('shop'),
        ]);
    }
}
