<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'details' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->paymentMethods()->create($data);

        return back();
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'details' => ['required', 'string', 'max:500'],
        ]);

        $paymentMethod->update($data);

        return back();
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->user_id !== $request->user()->id) {
            abort(403);
        }

        $paymentMethod->delete();

        return back();
    }
}
