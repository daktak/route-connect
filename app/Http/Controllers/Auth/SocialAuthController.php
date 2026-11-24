<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $allowed = ['google', 'github', 'strava'];

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, $this->allowed, true), 404);

        try {
            $driver = Socialite::driver($provider);

            if ($provider === 'strava') {
                $driver->scopes(['read', 'activity:read']);
            }

            return $driver->redirect();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors(['oauth' => ucfirst($provider).' login is not configured.']);
        }
    }

    public function callback(string $provider)
    {
        abort_unless(in_array($provider, $this->allowed, true), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors(['oauth' => 'Unable to authenticate with '.ucfirst($provider).'.']);
        }

        $email = $socialUser->getEmail();

        if (! $email) {
            return redirect()->route('login')->withErrors(['oauth' => ucfirst($provider).' did not return an email address.']);
        }

        $user = User::where('oauth_provider', $provider)->where('oauth_id', (string) $socialUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                // Link provider to existing account if not already linked to another provider.
                if ($user->oauth_provider && $user->oauth_provider !== $provider) {
                    return redirect()->route('login')->withErrors(['oauth' => 'This email is already linked to '.$user->oauth_provider.'.']);
                }

                $user->forceFill([
                    'oauth_provider' => $provider,
                    'oauth_id' => (string) $socialUser->getId(),
                    'avatar_url' => $socialUser->getAvatar(),
                ])->save();
            } else {
                $user = User::create([
                    'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: 'User',
                    'email' => $email,
                    'password' => null,
                    'email_verified_at' => now(),
                    'oauth_provider' => $provider,
                    'oauth_id' => (string) $socialUser->getId(),
                    'avatar_url' => $socialUser->getAvatar(),
                ]);
            }
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }
}
