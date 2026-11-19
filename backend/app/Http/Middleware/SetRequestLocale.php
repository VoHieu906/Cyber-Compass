<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the language the frontend is showing (Accept-Language: en | vi) for validation
 * messages and other translated responses. Unsupported languages fall back to English.
 */
final class SetRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $preferred = $request->getPreferredLanguage(array_column(Locale::cases(), 'value'));
        app()->setLocale($preferred ?? Locale::English->value);

        return $next($request);
    }
}
