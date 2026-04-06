<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function update(Request $request, Client $client): RedirectResponse
    {
        if ($client->user_id !== $request->user()->id) {
            abort(403);
        }

        $countryCodes = array_keys(config('countries', []));

        $isBusiness = filter_var($request->input('is_business'), FILTER_VALIDATE_BOOLEAN);

        $rules = [
            'is_business' => ['required', 'boolean'],
            'country' => ['required', 'string', 'size:2', Rule::in($countryCodes)],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
        ];

        if ($isBusiness) {
            $rules['business_name'] = ['required', 'string', 'max:255'];
            $rules['vat_number'] = ['nullable', 'string', 'max:64'];
        } else {
            $rules['first_name'] = ['required', 'string', 'max:100'];
            $rules['last_name'] = ['required', 'string', 'max:100'];
        }

        $data = $request->validate($rules);

        $client->is_business = $isBusiness;

        if ($isBusiness) {
            $client->business_name = $data['business_name'];
            $client->name = $data['business_name'];
            $client->first_name = null;
            $client->last_name = null;
            $client->vat_number = $data['vat_number'] ?? null;
        } else {
            $client->first_name = $data['first_name'];
            $client->last_name = $data['last_name'];
            $client->name = trim($data['first_name'].' '.$data['last_name']);
            $client->business_name = null;
            $client->vat_number = null;
        }

        $client->country = $data['country'];
        $client->street = $data['street'];
        $client->city = $data['city'];
        $client->postal_code = $data['postal_code'];
        $client->email = $data['email'] ?? null;

        $client->save();

        return back();
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        if ($client->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($client->invoices()->exists()) {
            return back()->withErrors(['client' => 'This client has existing invoices and cannot be deleted.']);
        }

        $client->delete();

        return back();
    }
}
