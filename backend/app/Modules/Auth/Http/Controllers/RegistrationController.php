<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\RegisterUser;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class RegistrationController extends Controller
{
    /**
     * Register.
     *
     * Creates the account and signs it in.
     *
     * @unauthenticated
     */
    public function __invoke(RegisterRequest $request, RegisterUser $register): JsonResponse
    {
        $user = $register->handle(
            $request->safe()->only(['name', 'email', 'password']),
            Locale::tryFrom(app()->getLocale()) ?? Locale::English,
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new UserResource($user->load('profile')))->response()->setStatusCode(201);
    }
}
