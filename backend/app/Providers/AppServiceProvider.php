<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Content\Blocks\BlockRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The single source of lesson block types (ARCHITECTURE D17).
        $this->app->singleton(BlockRegistry::class, fn () => BlockRegistry::default());
    }

    public function boot(): void
    {
        // Fail loudly in development on lazy loading (N+1), silent attribute drops and missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)->max(128)->letters()->numbers());

        // Password reset emails link to the frontend, which posts the token back to the API.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('app.frontend_url')
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));

        $this->configureRateLimits();
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        // Login has its own per-email lockout in AttemptLogin; these cover the other auth endpoints.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('password', fn (Request $request) => Limit::perMinute(5)
            ->by(($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
