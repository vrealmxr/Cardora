<?php

namespace App\Providers;

use App\Models\Listing;
use App\Observers\ListingObserver;
use App\Support\AdminKeyValueState;
use Filament\Forms\Components\KeyValue;
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
        Listing::observe(ListingObserver::class);

        if (! class_exists(KeyValue::class)) {
            return;
        }

        KeyValue::configureUsing(function (KeyValue $component): void {
            $component
                ->formatStateUsing(static fn (mixed $state): array => AdminKeyValueState::flattenForForm($state))
                ->dehydrateStateUsing(static fn (mixed $state): array => AdminKeyValueState::inflateForStorage($state));
        });
    }
}
