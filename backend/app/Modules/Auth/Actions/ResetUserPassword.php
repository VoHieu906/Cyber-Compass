<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResetUserPassword
{
    /**
     * @param  array{token: string, email: string, password: string}  $input
     */
    public function handle(array $input): void
    {
        $status = Password::reset(
            [...$input, 'email' => mb_strtolower($input['email'])],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // A reset means the old password may be known to someone else: end every session.
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }
    }
}
