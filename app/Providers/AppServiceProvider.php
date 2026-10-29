<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Location;
use App\Models\LookupProvider;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\TaxCategory;
use App\Models\User;
use App\Policies\ConfigurationPolicy;
use App\Policies\LookupProviderPolicy;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (is_file(resource_path('js/dist/isbn-scanner.js'))) {
            FilamentAsset::register([
                AlpineComponent::make('isbn-scanner', resource_path('js/dist/isbn-scanner.js')),
            ]);
        }
        if (is_file(resource_path('js/dist/book-lookup.js'))) {
            FilamentAsset::register([AlpineComponent::make('book-lookup', resource_path('js/dist/book-lookup.js'))]);
        }
        Gate::policy(LookupProvider::class, LookupProviderPolicy::class);
        Gate::define('receive-stock', fn (User $user) => $user->is_staff);
        foreach ([Category::class, Supplier::class, Location::class, SalesChannel::class, TaxCategory::class, Product::class] as $model) {
            Gate::policy($model, ConfigurationPolicy::class);
        }
    }
}
