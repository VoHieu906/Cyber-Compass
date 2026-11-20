<?php

namespace App\Modules\Admin\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Auth\Actions\EndUserSessions;
use Illuminate\Support\Facades\DB;

/**
 * The only way a role changes (role is not fillable). Ends the target's sessions, so a
 * demoted admin loses admin access immediately and a promoted learner signs in again
 * into the new role.
 */
final class ChangeUserRole
{
    public function __construct(
        private readonly AdminGuards $guards,
        private readonly EndUserSessions $endSessions,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, User $target, UserRole $role): User
    {
        if ($target->role === $role) {
            return $target;
        }

        return DB::transaction(function () use ($actor, $target, $role) {
            if ($role !== UserRole::Admin) {
                $this->guards->assertCanLoseAdminAccess($actor, $target, 'role');
            }

            $from = $target->role;
            $target->forceFill(['role' => $role])->save();
            $this->endSessions->handle($target);

            $this->audit->record($actor, 'user.role_changed', 'user', $target->id, [
                'role' => [$from->value, $role->value],
            ]);

            return $target;
        });
    }
}
