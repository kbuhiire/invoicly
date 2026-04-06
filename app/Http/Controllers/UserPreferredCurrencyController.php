<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferredCurrencyRequest;
use Illuminate\Http\RedirectResponse;

class UserPreferredCurrencyController extends Controller
{
    public function update(UpdatePreferredCurrencyRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->back();
    }
}
