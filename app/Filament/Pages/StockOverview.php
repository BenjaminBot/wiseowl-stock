<?php

namespace App\Filament\Pages;

use App\Models\StockBalance;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class StockOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.stock-overview';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_staff;
    }

    public function table(Table $table): Table
    {
        return $table->query(StockBalance::query()->with(['stockBatch.product', 'location', 'salesChannel']))->columns([
            TextColumn::make('stockBatch.product.name')->label('Product')->searchable(),
            TextColumn::make('stockBatch.product.isbn')->label('ISBN')->searchable(),
            TextColumn::make('stockBatch.id')->label('Batch'),
            TextColumn::make('stockBatch.condition')->label('Condition'),
            TextColumn::make('stockBatch.selling_price')->label('Price incl. tax')->money('EUR'),
            TextColumn::make('location.name')->label('Physical location'),
            TextColumn::make('salesChannel.name')->label('Allocation'),
            TextColumn::make('quantity')->label('On hand'),
            TextColumn::make('reserved_quantity')->label('Committed'),
            TextColumn::make('available')->state(fn (StockBalance $record) => $record->quantity - $record->reserved_quantity),
        ]);
    }
}
