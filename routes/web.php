<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserPreferredCurrencyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/developers', fn () => Inertia::render('Developers'))->name('developers');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/attachment', [InvoiceController::class, 'downloadAttachment'])->name('invoices.attachment');
    Route::post('invoices/preview', [InvoiceController::class, 'previewInvoice'])->name('invoices.preview');
    Route::resource('invoices', InvoiceController::class)->except(['show']);
    Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::patch('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
    Route::patch('/user/preferred-currency', [UserPreferredCurrencyController::class, 'update'])->name('user.preferred-currency');
});

Route::middleware('auth')->group(function () {
    Route::post('/recurring-invoices', [RecurringInvoiceController::class, 'store'])->name('recurring-invoices.store');
    Route::patch('/recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'update'])->name('recurring-invoices.update');
    Route::delete('/recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'destroy'])->name('recurring-invoices.destroy');

    Route::get('/settings/api-tokens', [ApiTokenController::class, 'index'])->name('settings.api-tokens.index');
    Route::post('/settings/api-tokens', [ApiTokenController::class, 'store'])->name('settings.api-tokens.store');
    Route::delete('/settings/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->name('settings.api-tokens.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/personal/edit', [SettingsController::class, 'editPersonal'])->name('settings.personal.edit');
    Route::patch('/settings/personal', [SettingsController::class, 'updatePersonal'])->name('settings.personal.update');
    Route::get('/settings/address/edit', [SettingsController::class, 'editAddress'])->name('settings.address.edit');
    Route::patch('/settings/address', [SettingsController::class, 'updateAddress'])->name('settings.address.update');
    Route::patch('/settings/invoice', [SettingsController::class, 'updateInvoice'])->name('settings.invoice.update');
    Route::patch('/settings/invoice/address', [SettingsController::class, 'updateInvoiceAddress'])->name('settings.invoice.address.update');
    Route::patch('/settings/invoice/phone', [SettingsController::class, 'updateInvoicePhone'])->name('settings.invoice.phone.update');
    Route::get('/settings/invoice/preview', [SettingsController::class, 'previewInvoicePdf'])->name('settings.invoice.preview');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
