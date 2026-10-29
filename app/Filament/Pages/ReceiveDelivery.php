<?php

namespace App\Filament\Pages;

use App\Actions\Catalogue\SaveIntakeBook;
use App\Actions\Inventory\ReceiveStock;
use App\Models\IntakeSession;
use App\Models\Product;
use App\Models\ReceiptLine;
use App\Models\SalesChannel;
use App\Models\TaxCategory;
use App\Services\Books\BookLookup;
use App\Services\Books\WebCoverSearch;
use App\Support\BookFormat;
use App\Support\BookLanguage;
use App\Support\Isbn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ReceiveDelivery extends Page
{
    protected string $view = 'filament.pages.receive-delivery';

    public ?array $data = [];

    public string $operationKey;

    public string $isbnInput = '';

    public ?string $scanMessage = null;

    #[Locked]
    public ?int $pendingProductId = null;

    public ?string $pendingProductName = null;

    #[Locked]
    public ?string $lookupIsbn = null;

    #[Locked]
    public ?string $lookupToken = null;

    #[Locked]
    public ?int $lookupProductId = null;

    #[Locked]
    public ?int $lookupVersion = null;

    #[Locked]
    public array $selectedCover = [];

    #[Locked]
    public array $metadataProvenance = [];

    #[Locked]
    public ?string $currentCoverUrl = null;

    #[Locked]
    public ?string $currentCoverMatchType = null;

    public ?array $catalogueData = [];

    public string $coverChoice = 'defer';

    public bool $catalogueEditorExpanded = true;

    private function beginBookLookup(string $isbn, ?Product $product, bool $force = false): void
    {
        if (! $force && $this->lookupIsbn === $isbn && $this->lookupProductId === $product?->id) {
            return;
        }
        $detail = $product?->bookDetail;
        $this->catalogueEditorExpanded = $product === null;
        $this->lookupIsbn = $isbn;
        $this->lookupToken = (string) Str::uuid();
        $this->lookupProductId = $product?->id;
        $this->lookupVersion = $product ? (int) $product->catalogue_version : null;
        $this->currentCoverUrl = $product?->coverImage?->url;
        $this->currentCoverMatchType = $product?->coverImage?->match_type;
        $this->coverChoice = $this->currentCoverUrl ? 'keep' : 'defer';
        $this->selectedCover = [];
        $this->metadataProvenance = ['metadata_source' => $detail?->metadata_source, 'source_url' => $detail?->source_url];
        $this->catalogueForm->fill([
            'name' => $product?->name ?? 'Book '.$isbn, 'description' => $product?->description, 'author_names' => $detail?->author_names ?? [],
            'publisher' => $detail?->publisher, 'published_date' => $detail?->published_date,
            'format' => $detail?->format, 'language' => $detail?->language,
            'format_original' => $detail?->format_original, 'language_original' => $detail?->language_original, 'needs_review' => $detail?->needs_review ?? true,
        ]);
        $this->dispatch('book-lookup-target', isbn: $isbn, token: $this->lookupToken);
    }

    public function canManageLookup(): bool
    {
        $product = $this->lookupProductId ? Product::find($this->lookupProductId) : null;

        return $product ? Gate::allows('update', $product) : Gate::allows('create', Product::class);
    }

    private function validateLookupToken(string $token): void
    {
        Gate::authorize('receive-stock');
        if (! $this->lookupIsbn || ! $this->lookupToken || ! hash_equals($this->lookupToken, $token)) {
            throw ValidationException::withMessages(['catalogueData.name' => 'These suggestions belong to an earlier scan. Try again for the current ISBN.']);
        }
    }

    public function useBookSuggestion(string $key, string $token): void
    {
        $this->validateLookupToken($token);
        abort_unless($this->canManageLookup(), 403);
        $suggestion = app(BookLookup::class)->cachedSuggestion($this->lookupIsbn, $key);
        if (! $suggestion) {
            throw ValidationException::withMessages(['catalogueData.name' => 'That suggestion is no longer available. Enter details manually.']);
        }
        $updates = ['name' => $suggestion['name']];
        foreach (['description' => 'description', 'author_names' => 'authors', 'publisher' => 'publisher', 'published_date' => 'published_date', 'format' => 'format', 'language' => 'language'] as $field => $key) {
            if (filled($suggestion[$key] ?? null)) {
                $updates[$field] = $suggestion[$key];
            }
        }
        foreach (['language', 'format'] as $field) {
            if (filled($suggestion[$field.'_original'] ?? null)) {
                $updates[$field.'_original'] = $suggestion[$field.'_original'];
            }
        }
        $this->catalogueData = array_replace($this->catalogueData ?? [], $updates);
        $this->metadataProvenance = ['metadata_source' => $suggestion['source'], 'source_url' => $suggestion['source_url']];
        $this->catalogueEditorExpanded = true;
        Notification::make()->success()->title('Details copied to draft')->body('Review the fields below, then save the catalogue entry.')->send();
    }

    public function chooseCover(string $key, string $token): void
    {
        $this->validateLookupToken($token);
        abort_unless($this->canManageLookup(), 403);
        if (str_starts_with($key, 'web:')) {
            $cover = app(WebCoverSearch::class)->cachedCover($this->lookupIsbn, $key);
        } else {
            $cover = app(BookLookup::class)->cachedExactCover($this->lookupIsbn, $key)
                ?? app(BookLookup::class)->cachedAlternativeCover($this->lookupIsbn, $key);
        }
        if (! $cover) {
            throw ValidationException::withMessages(['coverChoice' => 'That cover is no longer available. Choose another or defer.']);
        }
        $this->selectedCover = $cover;
        $this->coverChoice = 'candidate';
    }

    public function catalogueForm(Schema $schema): Schema
    {
        return $schema->statePath('catalogueData')->components([
            TextInput::make('name')->label('Book title')->required()->maxLength(255),
            Textarea::make('description')->rows(7)->maxLength(20000)->helperText('Review any web description before saving. You can edit it or leave it for later.')->columnSpanFull(),
            TagsInput::make('author_names')->label('Authors')->helperText('Press Enter after each author.')->nestedRecursiveRules(['string', 'max:255']),
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
            Toggle::make('needs_review')->label('Keep in catalogue review queue')->default(true),
            FileUpload::make('cover_upload')->label('Optional own cover image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->disk('public')->visibility('public')->directory('book-covers')->maxSize(5120)
                ->afterStateUpdated(function () {
                    $this->coverChoice = 'upload';
                }),
        ]);
    }

    public function saveCatalogue(): void
    {
        Gate::authorize('receive-stock');
        abort_unless($this->lookupIsbn && $this->canManageLookup(), 403);
        $isbn = $this->lookupIsbn;
        $data = $this->catalogueForm->getState();
        $product = app(SaveIntakeBook::class)->execute(auth()->user(), $isbn, $this->lookupProductId, $this->lookupVersion,
            $data + $this->metadataProvenance, $this->selectedCover ?: null, $this->coverChoice);
        $this->beginBookLookup($isbn, $product, force: true);
        if (filled($this->data['product_id'] ?? null) && (int) $this->data['product_id'] !== $product->id) {
            $this->pendingProductId = $product->id;
            $this->pendingProductName = $product->name;
            $this->scanMessage = 'Catalogue entry saved. Choose Switch book to receive it, or keep your current item.';
        } else {
            if ((int) ($this->data['product_id'] ?? 0) !== $product->id) {
                $this->prepareProduct($product);
            }
            $this->matchedResult($product);
        }
        Notification::make()->success()->title('Catalogue details saved')->body('Stock changes only when you receive the item.')->send();
    }

    public function identifyIsbn(mixed $input = null): array
    {
        Gate::authorize('receive-stock');
        $this->resetValidation('isbnInput');
        $this->pendingProductId = null;
        $this->pendingProductName = null;
        try {
            $value = Validator::make(['isbnInput' => $input ?? $this->isbnInput], ['isbnInput' => ['required', 'string', 'max:32']])->validate();
            $isbn = Isbn::canonical($value['isbnInput']);
        } catch (ValidationException) {
            $this->addError('isbnInput', 'Enter a valid book ISBN-10 or ISBN-13.');

            return $this->scanResult('invalid', 'Invalid book ISBN. The current item has not changed.');
        }

        $this->isbnInput = $isbn;
        $product = Product::where('isbn', $isbn)->first();
        $this->beginBookLookup($isbn, $product);
        if (! $product) {
            return $this->scanResult('not_found', 'No catalogue product matches ISBN '.$isbn.'. Review online suggestions or enter a provisional book below. The current receiving item has not changed.');
        }
        if (filled($this->data['product_id'] ?? null) && (int) $this->data['product_id'] !== $product->id) {
            $this->pendingProductId = $product->id;
            $this->pendingProductName = $product->name;

            return $this->scanResult('pending', 'Book found: '.$product->name.'. Switch books to clear the current item details, or keep the current item.');
        }
        if ((int) ($this->data['product_id'] ?? 0) !== $product->id) {
            $this->prepareProduct($product);
        }

        return $this->matchedResult($product);
    }

    public function confirmScannedProduct(): array
    {
        Gate::authorize('receive-stock');
        $product = $this->pendingProductId ? Product::find($this->pendingProductId) : null;
        if (! $product) {
            return $this->scanResult('not_found', 'That product is no longer available. Scan again.');
        }
        $this->prepareProduct($product);

        return $this->matchedResult($product);
    }

    public function cancelScannedProduct(): void
    {
        Gate::authorize('receive-stock');
        $this->pendingProductId = null;
        $this->pendingProductName = null;
        $this->isbnInput = Product::find($this->data['product_id'] ?? null)?->isbn ?? '';
        $product = Product::find($this->data['product_id'] ?? null);
        if ($product?->isbn) {
            $this->beginBookLookup($product->isbn, $product);
        }
        $this->scanMessage = 'Current item kept. No quantities were changed.';
    }

    private function prepareProduct(Product $product): void
    {
        $sessionId = $this->data['intake_session_id'] ?? null;
        $session = IntakeSession::find($sessionId);
        $rows = collect($session?->destinations ?? [])->map(fn ($row) => ['sales_channel_id' => $row['sales_channel_id'], 'quantity' => 0])->all();
        if ($unallocated = SalesChannel::where('is_system', true)->where('type', 'unallocated')->first()) {
            $rows[] = ['sales_channel_id' => $unallocated->id, 'quantity' => 0];
        }
        $this->form->fill([
            'intake_session_id' => $sessionId, 'product_id' => $product->id, 'quantity' => 1,
            'condition' => $session?->default_condition ?? 'new', 'cost_basis' => 'exclusive', 'cost_mode' => 'per_copy',
            'price_confirmed' => false, 'additional_quantity_confirmed' => false, 'allocations' => $rows,
        ]);
        $this->operationKey = (string) Str::uuid();
        $this->pendingProductId = null;
        $this->pendingProductName = null;
        $this->isbnInput = $product->isbn ?? '';
        $this->resetValidation();
    }

    private function matchedResult(Product $product): array
    {
        $received = ReceiptLine::where('intake_session_id', $this->data['intake_session_id'] ?? null)->where('product_id', $product->id)->sum('quantity');
        $message = 'Selected '.$product->name.'. Confirm quantity, price and allocation, then receive.';
        if ($received > 0) {
            $message .= ' Already received '.$received.' copies in this delivery. Adding more requires the additional-quantity checkbox.';
        }

        return $this->scanResult('matched', $message);
    }

    private function scanResult(string $status, string $message): array
    {
        $this->scanMessage = $message;

        return ['status' => $status, 'message' => $message];
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_staff;
    }

    public function mount(): void
    {
        $this->operationKey = (string) Str::uuid();
        $this->form->fill(['quantity' => 1, 'condition' => 'new', 'cost_basis' => 'exclusive', 'cost_mode' => 'per_copy']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Select::make('intake_session_id')->label('Delivery session')->options(fn () => IntakeSession::where('status', 'open')->pluck('reference', 'id'))->required()->live()->afterStateUpdated(function ($state, Set $set) {
                $session = IntakeSession::find($state);
                $set('condition', $session?->default_condition ?? 'new');
                $rows = collect($session?->destinations ?? [])->map(fn ($row) => ['sales_channel_id' => $row['sales_channel_id'], 'quantity' => 0])->all();
                if ($unallocated = SalesChannel::where('is_system', true)->where('type', 'unallocated')->first()) {
                    $rows[] = ['sales_channel_id' => $unallocated->id, 'quantity' => 0];
                }
                $set('allocations', $rows);
            }),
            Select::make('product_id')->label('Product / ISBN')->options(fn () => Product::orderBy('name')->limit(200)->get()->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.($p->isbn ?? $p->sku)]))->searchable()->getSearchResultsUsing(fn (string $search) => Product::where('name', 'ilike', '%'.$search.'%')->orWhere('isbn', 'like', '%'.$search.'%')->orWhere('sku', 'ilike', '%'.$search.'%')->limit(30)->get()->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.($p->isbn ?? $p->sku)]))->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)->live()->afterStateUpdated(function ($state) {
                if ($product = Product::find($state)) {
                    $this->prepareProduct($product);
                    $this->matchedResult($product);
                    if ($product->isbn) {
                        $this->beginBookLookup($product->isbn, $product);
                    }
                }
            })->required(),
            TextInput::make('quantity')->label('Additional quantity received')->integer()->minValue(1)->maxValue(1000000)->required(),
            Select::make('condition')->options(['new' => 'New', 'almost_new' => 'Almost new', 'used' => 'Used'])->required(),
            TextInput::make('cost_input')->label('Purchase cost (EUR)')->inputMode('decimal')->required(),
            Select::make('cost_mode')->options(['per_copy' => 'Per copy', 'line_total' => 'Total for this line'])->required(),
            Select::make('cost_basis')->options(['exclusive' => 'Excluding tax', 'inclusive' => 'Including tax'])->required(),
            Select::make('purchase_tax_category_id')->label('Purchase tax')->options(fn () => TaxCategory::pluck('name', 'id'))->required(),
            TextInput::make('selling_price')->label('Selling price per copy, including tax (EUR)')->inputMode('decimal')->required()->live(onBlur: true)->afterStateUpdated(fn (Set $set) => $set('price_confirmed', false)),
            Select::make('sales_tax_category_id')->label('Sales tax')->options(fn () => TaxCategory::pluck('name', 'id'))->required(),
            Checkbox::make('price_confirmed')->label('I confirm this batch selling price')->accepted()->required(),
            Repeater::make('allocations')->schema([
                Select::make('sales_channel_id')->label('Destination')->options(fn () => SalesChannel::pluck('name', 'id'))->required()->distinct(),
                TextInput::make('quantity')->integer()->minValue(0)->required(),
            ])->minItems(1)->required()->columnSpanFull(),
            Checkbox::make('additional_quantity_confirmed')->label('If already received in this session: I am adding more copies as a new line'),
        ]);
    }

    public function receive(): void
    {
        abort_unless(static::canAccess(), 403);
        if ($this->pendingProductId !== null) {
            throw ValidationException::withMessages(['isbnInput' => 'Choose Switch book or Keep current item before receiving.']);
        }
        $input = $this->form->getState();
        $receipt = app(ReceiveStock::class)->execute(auth()->user(), $input + ['idempotency_key' => $this->operationKey]);
        Notification::make()->success()->title('Received '.$receipt->quantity.' copies')->body('Receipt #'.$receipt->id.' saved. Stock remains at the receiving location.')->send();
        $session = $input['intake_session_id'];
        $rows = array_map(fn ($row) => ['sales_channel_id' => $row['sales_channel_id'], 'quantity' => 0], $input['allocations']);
        $this->operationKey = (string) Str::uuid();
        $this->form->fill(['intake_session_id' => $session, 'quantity' => 1, 'condition' => IntakeSession::findOrFail($session)->default_condition, 'cost_basis' => 'exclusive', 'cost_mode' => 'per_copy', 'allocations' => $rows]);
        $this->isbnInput = '';
        $this->scanMessage = null;
        $this->pendingProductId = null;
        $this->pendingProductName = null;
        $this->lookupIsbn = null;
        $this->lookupToken = null;
        $this->lookupProductId = null;
        $this->selectedCover = [];
        $this->currentCoverUrl = null;
        $this->dispatch('book-lookup-target', isbn: null, token: null);
        $this->dispatch('intake-item-received');
    }
}
