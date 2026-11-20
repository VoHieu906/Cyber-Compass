<?php

namespace App\Modules\Admin\Policies;

use App\Models\User;

/**
 * Who may manage accounts. One admin role today (assumption A1), but each action has
 * its own method so a finer split (support, editor) changes only this class.
 * The safety rules (no self-demotion, last active admin) are business rules in the
 * actions and answer 422 with a reason, not 403.
 */
final class UserAdminPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function suspend(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function sendPasswordReset(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function viewAuditLog(User $actor): bool
    {
        return $actor->isAdmin();
    }
}
