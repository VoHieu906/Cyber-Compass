<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Session login with per-account brute-force protection: 5 failed attempts per
 * email and IP lock that pair for a minute. Failures never reveal whether the
 * email exists. A suspended account is told so only after the correct password,
 * so the message reveals nothing to someone guessing.
 */
final class AttemptLogin
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function handle(Request $request, string $email, string $password, bool $remember): void
    {
        $key = $this->throttleKey($email, $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ])->status(429);
        }

        $guard = Auth::guard('web');

        if (! $guard->validate(['email' => mb_strtolower($email), 'password' => $password])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        /** @var User $user */
        $user = $guard->getLastAttempted();
        RateLimiter::clear($key);

        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['email' => __('auth.suspended')]);
        }

        $guard->login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
    }

    private function throttleKey(string $email, ?string $ip): string
    {
        return 'login:'.Str::transliterate(mb_strtolower($email)).'|'.$ip;
    }
}
