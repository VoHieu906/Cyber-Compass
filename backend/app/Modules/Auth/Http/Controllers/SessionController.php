<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\AttemptLogin;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class SessionController extends Controller
{
    /**
     * Log in.
     *
     * Starts a cookie session. Call `GET /sanctum/csrf-cookie` first and send `X-XSRF-TOKEN`.
     * Five failed attempts per email and IP lock that pair for a minute (429).
     *
     * @unauthenticated
     */
    public function store(LoginRequest $request, AttemptLogin $login): UserResource
    {
        $login->handle(
            $request,
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        );

        return new UserResource($request->user()->load('profile'));
    }

    /**
     * Log out.
     *
     * Ends the session and rotates the CSRF token.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
