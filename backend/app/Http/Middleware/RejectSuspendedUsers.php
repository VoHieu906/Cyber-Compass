<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspending a user already deletes their sessions and rotates their remember token
 * (Admin\Actions\SuspendUser). This is the second line: any request that still arrives
 * authenticated as a suspended user is signed out and answered 401.
 */
final class RejectSuspendedUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        // Only stateful (SPA) requests carry a session; nothing else can be signed in here.
        if ($request->hasSession() && $guard->user()?->isSuspended()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
