<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Rate limiter global: 60 request per menit per IP
        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Rate limiter login: 5 percobaan per menit per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\DocumentImportBatch::class,
            \App\Policies\DocumentImportBatchPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\DocumentImportItem::class,
            \App\Policies\DocumentImportItemPolicy::class
        );
    }
}
