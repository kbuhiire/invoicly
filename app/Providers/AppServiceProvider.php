<?php

namespace App\Providers;

use App\Events\InvoiceReconciled;
use App\Events\PaymentReceived;
use App\Listeners\PublishWebhookEvents;
use App\Listeners\RecalculateClientBehaviorOnPayment;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        if (
            str_starts_with(rtrim((string) config('app.url'), '/'), 'https://') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ) {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);
        $this->configureRateLimiting();

        // Refresh a client's credit/behaviour aggregates as soon as a payment lands.
        Event::listen(PaymentReceived::class, RecalculateClientBehaviorOnPayment::class);

        // Fan domain events out to users' outbound webhook subscriptions (Phase 5).
        Event::listen(PaymentReceived::class, [PublishWebhookEvents::class, 'onPaymentReceived']);
        Event::listen(InvoiceReconciled::class, [PublishWebhookEvents::class, 'onInvoiceReconciled']);
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
