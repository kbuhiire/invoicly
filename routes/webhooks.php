<?php

use App\Http\Controllers\Webhooks\IncomingPaymentController;
use App\Http\Controllers\Webhooks\TwilioStatusController;
use Illuminate\Support\Facades\Route;

/*
 * Inbound webhooks. These are authenticated per-request (HMAC / provider
 * signature), NOT via Sanctum — the caller is an external system, not a
 * logged-in user. Registered with the 'webhooks' prefix in bootstrap/app.php.
 */

// External payment events -> Phase 2 auto-reconciliation.
Route::post('incoming/{integration}/payments', IncomingPaymentController::class)
    ->middleware('webhook.signature')
    ->name('webhooks.incoming.payments');

// Twilio SMS/WhatsApp delivery + read receipts -> reminder_logs (Phase 4).
Route::post('twilio/status', TwilioStatusController::class)
    ->name('webhooks.twilio.status');
