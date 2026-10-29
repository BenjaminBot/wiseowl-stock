<?php

namespace App\Filament\Resources\Products\Tables;

use App\Support\BookFormat;
use App\Support\BookLanguage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['coverImage', 'bookDetail']))
            ->columns([
                ImageColumn::make('coverImage.url')->label('Cover')->imageHeight(64),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('isbn')
                    ->searchable(),
                TextColumn::make('barcode')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->searchable(),
                TextColumn::make('bookDetail.language')->label('Language')->formatStateUsing(fn ($state) => BookLanguage::options()[$state] ?? $state)->sortable()->toggleable(),
                TextColumn::make('bookDetail.format')->label('Book format')->formatStateUsing(fn ($state) => BookFormat::options()[$state] ?? $state)->sortable()->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('language')->label('Language')->options(BookLanguage::options())->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $value) => $query->whereHas('bookDetail', fn (Builder $details) => $details->where('language', $value)))),
                SelectFilter::make('format')->label('Book format')->options(BookFormat::options())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $value) => $query->whereHas('bookDetail', fn (Builder $details) => $details->where('format', $value)))),
                Filter::make('catalogue_review')->label('Missing cover/description or details to review')->query(fn (Builder $query) => $query->where('type', 'book')->where(fn (Builder $books) => $books->whereNull('description')->orWhere('description', '')->orWhereDoesntHave('coverImage')->orWhereDoesntHave('bookDetail')->orWhereHas('bookDetail', fn (Builder $details) => $details->where('needs_review', true))
                )),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
