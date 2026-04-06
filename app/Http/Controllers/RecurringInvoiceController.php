<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringInvoiceRequest;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class RecurringInvoiceController extends Controller
{
    public function store(StoreRecurringInvoiceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Mark the chosen invoice as a template if it isn't already
        Invoice::where('id', $data['template_invoice_id'])
            ->where('user_id', $user->id)
            ->update(['is_template' => true]);

        $user->recurringInvoices()->create([
            'template_invoice_id' => $data['template_invoice_id'],
            'name' => $data['name'],
            'frequency' => $data['frequency'],
            'next_run_at' => $data['next_run_at'],
        ]);

        return Redirect::route('settings.index', ['tab' => 'automation'])
            ->with('status', 'automation-created');
    }

    public function update(Request $request, RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->authorize('update', $recurringInvoice);

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:255'],
            'frequency' => ['sometimes', 'string', \Illuminate\Validation\Rule::in(['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'annually'])],
            'next_run_at' => ['sometimes', 'date'],
        ]);

        $recurringInvoice->update($validated);

        return Redirect::route('settings.index', ['tab' => 'automation'])
            ->with('status', 'automation-updated');
    }

    public function destroy(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->authorize('delete', $recurringInvoice);

        $recurringInvoice->delete();

        return Redirect::route('settings.index', ['tab' => 'automation'])
            ->with('status', 'automation-deleted');
    }
}
