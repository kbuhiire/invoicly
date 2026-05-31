<?php

use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CreditScoreController;
use App\Http\Controllers\Api\V1\ForecastController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\WebhookSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Invoice endpoints
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
        ->middleware('throttle:api-pdf');

    // Payment endpoints (auto-reconciliation)
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'store']);
    Route::get('invoices/{invoice}/payments', [PaymentController::class, 'forInvoice']);
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'storeForInvoice']);

    // Client (invoice recipient) endpoints
    Route::get('clients', [ClientController::class, 'index']);
    Route::post('clients', [ClientController::class, 'store']);
    Route::get('clients/{client}', [ClientController::class, 'show']);
    Route::get('clients/{client}/credit-score', [CreditScoreController::class, 'show']);

    // Cash-flow forecast
    Route::get('forecast', [ForecastController::class, 'index']);

    // Outbound webhook subscriptions (API-first management)
    Route::get('webhooks/subscriptions', [WebhookSubscriptionController::class, 'index']);
    Route::post('webhooks/subscriptions', [WebhookSubscriptionController::class, 'store']);
    Route::delete('webhooks/subscriptions/{subscription}', [WebhookSubscriptionController::class, 'destroy']);
});
