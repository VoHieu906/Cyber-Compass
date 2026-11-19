<?php

namespace App\Modules\Auth\Actions;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Session login with per-account brute-force protection: 5 failed attempts per
 * email and IP lock that pair for a minute. Failures never reveal whether the
 * email exists.
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

        if (! Auth::guard('web')->attempt(['email' => mb_strtolower($email), 'password' => $password], $remember)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
    }

    private function throttleKey(string $email, ?string $ip): string
    {
        return 'login:'.Str::transliterate(mb_strtolower($email)).'|'.$ip;
    }
}
