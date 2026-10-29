<x-filament::section heading="Catalogue details and cover">
    @php($lookupProviders = app(\App\Services\Books\LookupSettings::class)->client())
    <div class="mb-4 space-y-2 text-sm">
        @foreach ($lookupProviders as $provider)
            <p>{{ $provider['name'] }} — information: {{ $provider['metadata'] }}; images: {{ $provider['images'] }}{{ $provider['configured'] ? '' : ' (API key missing)' }}</p>
        @endforeach
        @can('viewAny', \App\Models\LookupProvider::class)
            <a href="{{ \App\Filament\Resources\LookupProviders\LookupProviderResource::getUrl() }}" class="underline">Configure lookup providers</a>
        @endcan
    </div>
    @if ($lookupIsbn)
        <p class="mb-4 text-sm">ISBN {{ $lookupIsbn }} · {{ $lookupProductId ? 'Existing catalogue book — changes need saving below.' : 'New book — review suggestions or continue with provisional details.' }}</p>
    @endif

    @if (is_file(resource_path('js/dist/book-lookup.js')))
        <div x-load x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('book-lookup').'&build='.hash_file('sha256', resource_path('js/dist/book-lookup.js')) }}"
             x-data="bookLookup({ endpoint: @js(url('/intake/book-lookup')), providers: @js($lookupProviders) })"
             x-on:book-lookup-target.window="load($event.detail)" class="space-y-4">
            <p x-show="loading" role="status">Looking for exact ISBN metadata and cover images… You can keep entering stock details.</p>
            <p x-show="message" x-text="message" role="status" class="text-sm"></p>
            <div x-show="$wire.lookupIsbn" class="space-y-2">
                @if ($lookupProviders['open_library']['metadata'] === 'manual' || $lookupProviders['open_library']['images'] === 'manual')
                    <x-filament::button type="button" color="gray" x-on:click="lookupOpenLibrary()" x-bind:disabled="loading">Search Open Library</x-filament::button>
                @endif
                <x-filament::button type="button" color="gray" x-on:click="lookupGoogleBooks()" x-bind:disabled="loading || !@js($lookupProviders['google_books']['available'])">Search Google Books</x-filament::button>
                <p class="text-sm" x-text="googleAutomatic ? 'Google Books uses the automatic capabilities configured by your manager.' : (googleRequested ? 'Google Books enabled for this book. Cached results are reused.' : 'Google Books is off for this book. Search only when needed to save quota.')"></p>
            </div>
            <button type="button" x-show="$wire.lookupIsbn && !loading" x-on:click="retry()" class="text-sm underline">Retry online lookup</button>

            <p x-show="authorsLoading" role="status" class="text-sm">Looking up author names… Stock entry can continue.</p>
            <p x-show="authorsMessage" x-text="authorsMessage" role="status" class="text-sm"></p>
            <button type="button" x-show="authorsMessage && !authorsLoading" x-on:click="loadAuthors()" class="text-sm underline">Retry authors</button>
            <p x-show="descriptionLoading" role="status" class="text-sm">Looking up a book description… Stock entry can continue.</p>
            <p x-show="descriptionMessage" x-text="descriptionMessage" role="status" class="text-sm"></p>
            <button type="button" x-show="descriptionMessage && !descriptionLoading" x-on:click="loadDescription()" class="text-sm underline">Retry description</button>
            <div class="grid gap-3 sm:grid-cols-2">
                <template x-for="suggestion in result.suggestions" :key="suggestion.key">
                    <article class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="font-medium" x-text="suggestion.name"></p>
                        <p class="text-sm" x-text="suggestion.authors.join(', ')"></p>
                        <p class="text-sm" x-text="[suggestion.publisher, suggestion.published_date, suggestion.format, suggestion.language].filter(Boolean).join(' · ')"></p>
                        <details x-show="suggestion.description" class="my-3 text-sm">
                            <summary>Preview description</summary>
                            <p x-text="suggestion.description" class="mt-2 whitespace-pre-line"></p>
                        </details>
                        <a :href="suggestion.source_url" target="_blank" rel="noopener noreferrer" class="text-sm underline" x-text="suggestion.source"></a>
                        @if ($this->canManageLookup())
                            <x-filament::button type="button" size="sm" color="gray" class="mt-3" x-on:click="$wire.useBookSuggestion(suggestion.key, token)" wire:loading.attr="disabled" wire:target="useBookSuggestion,chooseCover,saveCatalogue">Use these details</x-filament::button>
                        @endif
                    </article>
                </template>
            </div>

<h3 x-show="result.covers.length" class="font-medium">Covers for this ISBN</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <template x-for="cover in result.covers" :key="cover.key">
                    <article x-show="!cover.failed" class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" :class="$wire.selectedCover.key === cover.key ? 'ring-2 ring-primary-500' : ''">
                        <img :src="cover.url" :alt="cover.title" referrerpolicy="no-referrer" loading="lazy" x-on:load="cover.loaded = true" x-on:error="cover.failed = true" class="h-40 w-full object-contain">
                        <a :href="cover.source_url" target="_blank" rel="noopener noreferrer" class="mt-2 block text-sm underline" x-text="cover.source"></a>
                        @if ($this->canManageLookup())
                            <x-filament::button type="button" size="sm" color="gray" class="mt-2" x-bind:disabled="!cover.loaded" x-on:click="$wire.chooseCover(cover.key, token)" wire:loading.attr="disabled" wire:target="useBookSuggestion,chooseCover,saveCatalogue">Select cover</x-filament::button>
                        @endif
                    </article>
                </template>
            </div>
            <p x-show="!loading && result.covers.length && result.covers.every(cover => cover.failed)" class="text-sm">No cover images could be loaded. Upload your own or defer selection.</p>
            <x-filament::button type="button" color="gray" x-show="$wire.lookupIsbn" x-bind:disabled="loading || alternativesLoading" x-on:click="findMoreCovers()">Find more covers</x-filament::button>
            <p class="text-sm"><span x-text="(googleRequested || googleAutomatic) ? 'Find more covers includes Google Books title matches for this book.' : 'Find more covers uses Open Library. Choose Search Google Books to include Google.'"></span></p>
            <p x-show="alternativesLoading" role="status">Looking for covers from other editions…</p>
            <p x-show="alternativesMessage" x-text="alternativesMessage" role="status" class="text-sm"></p>
            <h3 x-show="alternativeCovers.length" class="font-medium">Alternative edition covers</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <template x-for="cover in alternativeCovers" :key="cover.key">
                    <article x-show="!cover.failed" class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" :class="$wire.selectedCover.key === cover.key ? 'ring-2 ring-primary-500' : ''">
                        <p class="mb-2 text-sm font-semibold text-warning-600">Possible edition — check your copy</p>
                        <p class="text-sm font-medium" x-text="cover.title"></p>
                        <p x-show="cover.match_basis === 'title'" class="text-xs">Matched by title, not the scanned ISBN</p>
                        <p class="text-sm" x-text="cover.edition_description"></p>
                        <p class="mb-2 text-xs" x-text="cover.edition_isbn ? 'ISBN ' + cover.edition_isbn : 'Edition ISBN unavailable'"></p>
                        <img :src="cover.url" :alt="cover.title" referrerpolicy="no-referrer" loading="lazy" x-on:load="cover.loaded = true" x-on:error="cover.failed = true" class="h-40 w-full object-contain">
                        <a :href="cover.source_url" target="_blank" rel="noopener noreferrer" class="mt-2 block text-sm underline" x-text="cover.source"></a>
                        @if ($this->canManageLookup())
                            <x-filament::button type="button" size="sm" color="gray" class="mt-2" x-bind:disabled="!cover.loaded" x-on:click="$wire.chooseCover(cover.key, token)" wire:loading.attr="disabled" wire:target="useBookSuggestion,chooseCover,saveCatalogue">Use this edition cover</x-filament::button>
                        @endif
                    </article>
                </template>
            </div>

            <p x-show="alternativeCovers.length && alternativeCovers.every(cover => cover.failed)" class="text-sm">Alternative cover images could not be loaded. Keep an exact-ISBN cover, upload your own, or defer.</p>
            <div x-show="$wire.lookupIsbn" class="space-y-3">
                <x-filament::button type="button" color="gray" x-bind:disabled="webLoading || !@js($lookupProviders['brave']['available'])" x-on:click="searchWebCovers()">Search web images (Brave)</x-filament::button>
                <p x-show="webLoading" role="status">Searching the web for this ISBN…</p>
                <p x-show="webMessage" x-text="webMessage" role="status" class="text-sm"></p>
                <div class="flex flex-wrap gap-4 text-sm">
                    <a :href="'https://www.google.com/search?udm=2&q=' + encodeURIComponent($wire.lookupIsbn)" target="_blank" rel="noopener noreferrer" class="underline">Open Google Images</a>
                    <a :href="'https://www.amazon.com/s?k=' + encodeURIComponent($wire.lookupIsbn)" target="_blank" rel="noopener noreferrer" class="underline">Search Amazon</a>
                </div>
                <p class="text-sm">Search links open separately. You can upload a cover you are permitted to use.</p>
            </div>
            <h3 x-show="webCovers.length" class="font-medium">Web image results — review required</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <template x-for="cover in webCovers" :key="cover.key">
                    <article x-show="!cover.failed" class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" :class="$wire.selectedCover.key === cover.key ? 'ring-2 ring-primary-500' : ''">
                        <p class="mb-2 text-sm font-semibold text-warning-600">Web result — verify book and edition</p>
                        <p class="text-sm font-medium" x-text="cover.title"></p>
                        <img :src="cover.preview_url" :alt="cover.title" referrerpolicy="no-referrer" loading="lazy" x-on:load="cover.loaded = true" x-on:error="cover.failed = true" class="h-40 w-full object-contain">
                        <a :href="cover.source_url" target="_blank" rel="noopener noreferrer" class="mt-2 block text-sm underline" x-text="cover.source_host"></a>
                        @if ($this->canManageLookup())
                            <x-filament::button type="button" size="sm" color="gray" class="mt-2" x-bind:disabled="!cover.loaded" x-on:click="$wire.chooseCover(cover.key, token)" wire:loading.attr="disabled" wire:target="useBookSuggestion,chooseCover,saveCatalogue">Use this web image</x-filament::button>
                        @endif
                    </article>
                </template>
            </div>


            <p x-show="webCovers.length && webCovers.every(cover => cover.failed)" class="text-sm">Web thumbnails could not be loaded. Use the source links or upload your own cover.</p>
        </div>
    @endif

    @if ($lookupIsbn)
        @if ($currentCoverUrl)
            <div class="my-4"><p class="text-sm">Current saved cover{{ $currentCoverMatchType === 'alternative' ? ' — other edition' : ($currentCoverMatchType === 'web' ? ' — web result' : '') }}</p><img src="{{ $currentCoverUrl }}" alt="Current book cover" referrerpolicy="no-referrer" class="h-32 object-contain"></div>
        @endif
        @if ($this->canManageLookup())
            <form wire:submit="saveCatalogue" x-on:submit="$dispatch('intake-stop-scanner', { resume: false })" class="mt-4 space-y-4">
                <fieldset x-bind:disabled="scannerBusy" wire:loading.attr="disabled" wire:target="identifyIsbn,saveCatalogue,useBookSuggestion,chooseCover" class="space-y-4">
                <x-filament::section heading="Edit book details or upload your own cover" collapsible :collapsed="! $catalogueEditorExpanded" wire:key="catalogue-editor-{{ $lookupToken }}-{{ (int) $catalogueEditorExpanded }}">
                    {{ $this->catalogueForm }}
                </x-filament::section>
                <fieldset class="flex flex-wrap gap-4">
                    @if ($currentCoverUrl)<label><input type="radio" wire:model="coverChoice" value="keep"> Keep saved cover</label>@endif
                    <label><input type="radio" wire:model="coverChoice" value="defer"> Defer cover selection</label>
                    <label><input type="radio" wire:model="coverChoice" value="upload"> Use own upload</label>
                    @if ($selectedCover)
                        <label><input type="radio" wire:model="coverChoice" value="candidate"> Selected {{ $selectedCover['source'] }} cover{{ ($selectedCover['match_type'] ?? 'exact') === 'alternative' ? ' — other edition, check your copy' : (($selectedCover['match_type'] ?? '') === 'web' ? ' — web result, verify against your copy' : '') }}</label>
                    @endif
                </fieldset>
                @error('coverChoice')<p role="alert" class="text-sm text-danger-600">{{ $message }}</p>@enderror
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveCatalogue,useBookSuggestion,chooseCover,identifyIsbn,receive">{{ $lookupProductId ? 'Save catalogue details' : 'Create book and select for intake' }}</x-filament::button>
                <p class="text-sm text-gray-500">This saves the catalogue entry only. Quantity, cost, selling price and allocation are confirmed separately when receiving.</p>
                </fieldset>
            </form>
        @else
            <p class="mt-4 text-sm">Ask a manager to create or update this catalogue entry. You can continue receiving existing products.</p>
        @endif
    @endif
</x-filament::section>
