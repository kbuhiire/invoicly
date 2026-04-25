<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    /**
     * All abilities that can be granted to a token.
     *
     * @var list<array{id: string, label: string, description: string}>
     */
    public const ABILITIES = [
        ['id' => 'invoices:read', 'label' => 'Read invoices', 'description' => 'List and view invoices and download PDFs'],
        ['id' => 'invoices:write', 'label' => 'Write invoices', 'description' => 'Create, update and delete invoices'],
        ['id' => 'clients:read', 'label' => 'Read clients', 'description' => 'List and view invoice recipients'],
        ['id' => 'clients:write', 'label' => 'Write clients', 'description' => 'Create invoice recipients'],
    ];

    public function index(Request $request): Response
    {
        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'created_at' => $token->created_at->format('M j, Y'),
            ]);

        return Inertia::render('Settings/ApiTokens', [
            'tokens' => $tokens,
            'abilities' => self::ABILITIES,
            'newToken' => session('new_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validAbilityIds = array_column(self::ABILITIES, 'id');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in($validAbilityIds)],
        ]);

        $token = $request->user()->createToken($data['name'], $data['abilities']);

        return redirect()
            ->route('settings.api-tokens.index')
            ->with('new_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()
            ->tokens()
            ->where('id', $tokenId)
            ->delete();

        return redirect()
            ->route('settings.api-tokens.index')
            ->with('success', 'Token revoked.');
    }
}
