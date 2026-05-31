<?php

use App\Console\Commands\GenerateRecurringInvoices;
use App\Console\Commands\RecalculateClientBehavior;
use App\Console\Commands\SendInvoiceReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateRecurringInvoices::class)->dailyAt('06:00');
Schedule::command(RecalculateClientBehavior::class)->dailyAt('02:00');
// Run after the nightly behaviour recompute so reminder timing uses fresh
// avg_days_to_pay, but at a civil hour for outbound messages.
Schedule::command(SendInvoiceReminders::class)->dailyAt('08:00');
