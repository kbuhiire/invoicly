<?php

use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ClientController;
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

    // Client (invoice recipient) endpoints
    Route::get('clients', [ClientController::class, 'index']);
    Route::post('clients', [ClientController::class, 'store']);
    Route::get('clients/{client}', [ClientController::class, 'show']);
});
