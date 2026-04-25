<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClientType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('clients:read'), 403, 'Token missing clients:read ability.');

        $user = $request->user();

        $query = Client::query()->where('user_id', $user->id);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where('name', 'like', '%'.$search.'%');
        }

        $clients = $query
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return ClientResource::collection($clients)->response();
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        abort_unless($request->user()->tokenCan('clients:read'), 403, 'Token missing clients:read ability.');

        abort_unless((int) $client->user_id === (int) $request->user()->id, 403, 'This client does not belong to your account.');

        return (new ClientResource($client))->response();
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('clients:write'), 403, 'Token missing clients:write ability.');

        $user = $request->user();
        $countryCodes = array_keys(config('countries'));

        $data = $request->validate([
            'type' => ['required', Rule::in(['external', 'invoicly'])],
            'is_business' => ['required', 'boolean'],
            'first_name' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf(fn () => ! $request->boolean('is_business')),
            ],
            'last_name' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf(fn () => ! $request->boolean('is_business')),
            ],
            'business_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->boolean('is_business')),
            ],
            'country' => ['required', 'string', 'size:2', Rule::in($countryCodes)],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:64'],
        ]);

        $isBusiness = (bool) $data['is_business'];
        $name = $isBusiness
            ? (string) $data['business_name']
            : trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        $client = Client::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => $data['type'],
            'is_business' => $isBusiness,
            'first_name' => $isBusiness ? null : ($data['first_name'] ?? null),
            'last_name' => $isBusiness ? null : ($data['last_name'] ?? null),
            'business_name' => $isBusiness ? $data['business_name'] : null,
            'country' => $data['country'],
            'street' => $data['street'],
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'email' => $data['email'] ?? null,
            'vat_number' => $isBusiness ? ($data['vat_number'] ?? null) : null,
        ]);

        return (new ClientResource($client))
            ->response()
            ->setStatusCode(201);
    }
}
