<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // General API limiter: 60 req/min per authenticated user, 10/min by IP for unauthenticated
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(10)->by($request->ip());
        });

        // Stricter limiter for PDF generation (CPU/memory heavy)
        RateLimiter::for('api-pdf', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?? $request->ip());
        });
    }
}
