<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'github'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::SUPPORTED_PROVIDERS), 404);

        $socialUser = Socialite::driver($provider)->user();

        $user = User::firstOrCreate(
            ['provider' => $provider, 'provider_id' => $socialUser->getId()],
            [
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email' => $socialUser->getEmail(),
                'email_verified_at' => now(),
            ]
        );

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
