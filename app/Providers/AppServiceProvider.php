<?php


namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sale;
use App\Observers\SaleObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the SalesSummaryService
        $this->app->singleton(\App\Services\SalesSummaryService::class, function ($app) {
            return new \App\Services\SalesSummaryService();
        });

        $this->app->singleton(\App\Services\DotMatrixPrinterService::class, function ($app) {
        return new \App\Services\DotMatrixPrinterService();
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the Sale observer
        Sale::observe(SaleObserver::class);
    }
}