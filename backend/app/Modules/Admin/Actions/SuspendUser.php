<?php

namespace App\Modules\Admin\Actions;

use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Auth\Actions\EndUserSessions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Blocks sign-in and ends every session at once. Learning data is kept untouched,
 * so reactivating restores the account exactly as it was.
 */
final class SuspendUser
{
    public function __construct(
        private readonly AdminGuards $guards,
        private readonly EndUserSessions $endSessions,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, User $target, string $reason): User
    {
        if ($target->isSuspended()) {
            throw ValidationException::withMessages(['user' => __('This account is already suspended.')]);
        }

        return DB::transaction(function () use ($actor, $target, $reason) {
            $this->guards->assertCanLoseAdminAccess($actor, $target, 'user');

            $target->forceFill(['suspended_at' => now(), 'suspension_reason' => $reason])->save();
            $this->endSessions->handle($target);

            $this->audit->record($actor, 'user.suspended', 'user', $target->id, ['reason' => $reason]);

            return $target;
        });
    }
}
