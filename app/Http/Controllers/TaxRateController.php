<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxRateController extends Controller
{
    public function store(StoreTaxRateRequest $request): RedirectResponse
    {
        $this->authorize('create', TaxRate::class);

        $data = $request->validated();

        DB::transaction(function () use ($request, $data) {
            if (! empty($data['is_default'])) {
                $request->user()->taxRates()->update(['is_default' => false]);
            }

            $request->user()->taxRates()->create([
                'name' => $data['name'],
                'rate' => $data['rate'],
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);
        });

        return back()->with('success', 'Tax rate added.');
    }

    public function update(UpdateTaxRateRequest $request, TaxRate $taxRate): RedirectResponse
    {
        $this->authorize('update', $taxRate);

        $data = $request->validated();

        DB::transaction(function () use ($request, $taxRate, $data) {
            if (! empty($data['is_default'])) {
                $request->user()->taxRates()
                    ->where('id', '!=', $taxRate->id)
                    ->update(['is_default' => false]);
            }

            $taxRate->update([
                'name' => $data['name'],
                'rate' => $data['rate'],
                'is_default' => (bool) ($data['is_default'] ?? $taxRate->is_default),
            ]);
        });

        return back()->with('success', 'Tax rate updated.');
    }

    public function destroy(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $this->authorize('delete', $taxRate);

        $taxRate->delete();

        return back()->with('success', 'Tax rate deleted.');
    }
}
