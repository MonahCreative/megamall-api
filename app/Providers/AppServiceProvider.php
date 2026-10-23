<?php

namespace App\Providers;

use App\Services\PricingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One pricing calculator per request, so running promotions are loaded only once.
        $this->app->scoped(PricingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
