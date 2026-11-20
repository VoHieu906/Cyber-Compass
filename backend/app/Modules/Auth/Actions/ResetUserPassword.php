<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final class ResetUserPassword
{
    public function __construct(private readonly EndUserSessions $endSessions) {}

    /**
     * @param  array{token: string, email: string, password: string}  $input
     */
    public function handle(array $input): void
    {
        $status = Password::reset(
            [...$input, 'email' => mb_strtolower($input['email'])],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();

                // A reset means the old password may be known to someone else: end every session.
                $this->endSessions->handle($user);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }
    }
}
