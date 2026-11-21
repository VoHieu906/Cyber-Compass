<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers for every API response (D42, docs/SECURITY_REVIEW.md). The API only ever returns JSON,
 * so its policy lets nothing load in it or frame it.
 *
 * The image's nginx (serversideup `server-opts.d/security.conf`, the same in development and
 * production) already sends X-Frame-Options, X-Content-Type-Options, Referrer-Policy and HSTS on
 * every response; repeating them here would send two, conflicting X-Frame-Options values.
 */
final class SecurityHeaders
{
    public const HEADERS = [
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'",
        'Cross-Origin-Resource-Policy' => 'same-site',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Scramble's documentation page (local development only) is HTML with scripts.
        if ($request->is('docs/*')) {
            return $response;
        }

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value, replace: false);
        }

        return $response;
    }
}
