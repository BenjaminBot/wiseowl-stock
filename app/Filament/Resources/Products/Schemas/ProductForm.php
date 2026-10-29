<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Support\BookFormat;
use App\Support\BookLanguage;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->label('SKU')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
            Textarea::make('description')->rows(7)->maxLength(20000)->columnSpanFull(),
            Select::make('type')->options(['book' => 'Book', 'other' => 'Other product'])->default('book')->required()->live(),
            TextInput::make('isbn')->label('ISBN')->helperText('ISBN-10 or ISBN-13; stored as canonical ISBN-13.')->maxLength(32),
            TextInput::make('barcode')->helperText('Optional non-ISBN product barcode.')->maxLength(255)->unique(ignoreRecord: true),
            Select::make('category_id')->relationship('category', 'name')->searchable()->preload(),
            Section::make('Book details')->relationship('bookDetail')->visible(fn (Get $get) => $get('type') === 'book')->schema([
                TagsInput::make('author_names')->label('Authors')->nestedRecursiveRules(['string', 'max:255']),
                TextInput::make('publisher')->maxLength(255), TextInput::make('published_date')->label('Publication date / year')->maxLength(64),
                Select::make('format')->label('Book format / binding')->options(BookFormat::options())->searchable()->native(false)
                    ->helperText('Match the physical copy. Leave blank if unknown.'),
                Select::make('language')->options(BookLanguage::options())->searchable()->native(false)
                    ->helperText('Language of the book, not the interface.'),
                TextInput::make('format_original')->label('Provider / previous format')->readOnly()->maxLength(255)
                    ->helperText('Unmatched values need a manual choice above.')
                    ->visible(fn ($state) => filled($state) && BookFormat::normalise($state) === null),
                TextInput::make('language_original')->label('Provider / previous language')->readOnly()->maxLength(64)
                    ->helperText('Unmatched values need a manual choice above.')
                    ->visible(fn ($state) => filled($state) && BookLanguage::normalise($state) === null),
                Toggle::make('needs_review')->label('Needs catalogue review')->default(true),
                Hidden::make('updated_by')->default(fn () => auth()->id())->dehydrateStateUsing(fn () => auth()->id()),
            ])->columnSpanFull(),
        ]);
    }
}
