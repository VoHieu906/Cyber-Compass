<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\ResetUserPassword;
use App\Modules\Auth\Http\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

final class PasswordResetController extends Controller
{
    /**
     * Request a password reset link.
     *
     * Always answers 202, whether or not the email has an account, so the endpoint
     * cannot be used to discover registered addresses.
     *
     * @unauthenticated
     */
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => mb_strtolower($request->string('email')->toString())]);

        return response()->json(['data' => ['message' => __('passwords.sent_generic')]], 202);
    }

    /**
     * Reset the password.
     *
     * Uses the token from the emailed link. Ends all of the user's sessions.
     *
     * @unauthenticated
     */
    public function update(ResetPasswordRequest $request, ResetUserPassword $reset): JsonResponse
    {
        $reset->handle($request->safe()->only(['token', 'email', 'password']));

        return response()->json(['data' => ['message' => __('passwords.reset')]]);
    }
}
