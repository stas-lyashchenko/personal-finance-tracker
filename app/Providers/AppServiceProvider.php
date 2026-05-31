<?php

namespace App\Providers;

use App\Support\CurrencyExchangeService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('*', function ($view) {
            $currencyCode = auth()->user()?->currency ?? 'UAH';

            $view->with('currencyCode', $currencyCode);
            $view->with('currencySymbol', CurrencyExchangeService::symbol($currencyCode));
        });
    }
}
