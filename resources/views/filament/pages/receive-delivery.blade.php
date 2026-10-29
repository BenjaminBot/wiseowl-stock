<x-filament-panels::page>
    <div x-data="{ scannerBusy: false }" x-on:intake-scanner-busy.window="scannerBusy = $event.detail.busy" class="space-y-6">
    <p>Scan, photograph, or enter an ISBN. Select an existing book or review online suggestions for a new one, then confirm its receiving details.</p>

    <x-filament::section heading="Identify a book">
        @if (is_file(resource_path('js/dist/isbn-scanner.js')))
            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('isbn-scanner').'&build='.hash_file('sha256', resource_path('js/dist/isbn-scanner.js')) }}"
                x-data="isbnScanner()"
                x-on:intake-item-received.window="afterReceive()"
                x-on:intake-stop-scanner.window="if ($event.detail.resume === false) resumeAfterSave = false; stopCamera()"
                class="space-y-4"
            >
                <div class="flex flex-wrap gap-3" wire:loading.class="pointer-events-none opacity-50" wire:target="receive,identifyIsbn,confirmScannedProduct">
                    <x-filament::button type="button" x-on:click="startCamera()" x-bind:disabled="busy || cameraOpen">Scan with camera</x-filament::button>
                    <x-filament::button type="button" color="gray" x-on:click="resumeAfterSave = false; stopCamera()" x-bind:disabled="!cameraOpen">Stop camera</x-filament::button>
                </div>
                <div wire:ignore x-ref="reader" id="isbn-reader-{{ $this->getId() }}" class="w-full"></div>
                <div>
                    <label for="barcode-photo-{{ $this->getId() }}" class="block text-sm font-medium">Take a barcode photo or choose an image</label>
                    <input id="barcode-photo-{{ $this->getId() }}" type="file" accept="image/*" capture="environment" x-on:change="readPhoto($event)" x-bind:disabled="busy" wire:loading.attr="disabled" wire:target="receive,identifyIsbn,confirmScannedProduct" class="mt-2 block w-full text-base">
                </div>
                <p role="status" aria-live="polite" x-text="status" class="text-sm"></p>
                <p class="text-sm text-gray-500">Photos stay on your device. Live camera scanning requires trusted HTTPS; photo capture and manual ISBN entry are available on a local HTTP connection.</p>
            </div>
        @else
            <p>Camera scanning is unavailable. Enter the ISBN below.</p>
        @endif
        <form wire:submit="identifyIsbn" x-on:submit="$dispatch('intake-stop-scanner', { resume: false })" class="mt-4 space-y-3">
            <fieldset x-bind:disabled="scannerBusy" wire:loading.attr="disabled" wire:target="receive,identifyIsbn,confirmScannedProduct" class="space-y-3">
            <label for="manual-isbn" class="block text-sm font-medium">ISBN-10 or ISBN-13</label>
            <x-filament::input.wrapper>
                <x-filament::input id="manual-isbn" wire:model="isbnInput" autocomplete="off" placeholder="9780140328721" />
            </x-filament::input.wrapper>
            @error('isbnInput') <p role="alert" class="text-sm text-danger-600">{{ $message }}</p> @enderror
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="receive,identifyIsbn,confirmScannedProduct">Find ISBN</x-filament::button>
            </fieldset>
        </form>
        @if ($scanMessage)
            <p role="status" class="mt-4 text-sm">{{ $scanMessage }}</p>
        @endif
        @if ($pendingProductId)
            <div class="mt-4 space-y-3" role="alert">
                <p>Switch to <strong>{{ $pendingProductName }}</strong>? Current quantity, cost, price and split will be cleared. Your delivery session stays selected.</p>
                <x-filament::button type="button" wire:click="confirmScannedProduct" wire:loading.attr="disabled">Switch book</x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="cancelScannedProduct" wire:loading.attr="disabled">Keep current item</x-filament::button>
            </div>
        @endif
    </x-filament::section>
    @include('filament.partials.book-lookup')
    <form wire:submit="receive" x-on:submit="$dispatch('intake-stop-scanner', { resume: true })" class="space-y-6">
        <fieldset x-bind:disabled="scannerBusy" wire:loading.attr="disabled" wire:target="receive,identifyIsbn,confirmScannedProduct" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="receive">Receive and next item</x-filament::button>
        </fieldset>
    </form>
    </div>
</x-filament-panels::page>
