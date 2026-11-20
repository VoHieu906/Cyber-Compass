<?php

namespace App\Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for every /api/v1/admin route. Runs after auth:sanctum, so a guest has already
 * been answered 401; a signed-in learner gets 403 (docs/PHASE_3_5_CONTENT_AND_ADMIN.md C6).
 * Each action is still checked by its policy, so this is not the only line.
 */
final class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
