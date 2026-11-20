<?php

namespace App\Modules\Admin\Actions;

use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Sends the user the same reset email they could request themselves. Admins never see
 * or set passwords; the link goes only to the account's own address.
 */
final class SendPasswordResetLink
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, User $target): void
    {
        if ($target->isSuspended()) {
            // The reset would succeed but sign-in would still be refused: reactivate first.
            throw ValidationException::withMessages(['user' => __('Reactivate this account before sending a reset link.')]);
        }

        $status = Password::sendResetLink(['email' => $target->email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['user' => __($status)])->status(429);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['user' => __($status)]);
        }

        $this->audit->record($actor, 'user.password_reset_sent', 'user', $target->id);
    }
}
