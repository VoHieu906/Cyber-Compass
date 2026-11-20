<?php

namespace App\Modules\Admin\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Rules that keep the platform administrable (docs/PHASE_3_5_CONTENT_AND_ADMIN.md §8):
 * an admin can't remove their own access, and the last active admin can't lose theirs.
 * Call inside a transaction: the admin rows are locked, so two admins demoting each
 * other at the same moment can't both succeed.
 */
final class AdminGuards
{
    /** @throws ValidationException keyed by $field */
    public function assertCanLoseAdminAccess(User $actor, User $target, string $field): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([$field => __('You cannot remove your own admin access. Ask another admin.')]);
        }

        if (! $target->isAdmin() || $target->isSuspended()) {
            return;
        }

        $activeAdmins = User::query()->activeAdmins()->lockForUpdate()->pluck('id');

        if ($activeAdmins->count() <= 1 && $activeAdmins->contains($target->id)) {
            throw ValidationException::withMessages([$field => __('This is the last active admin. Promote another admin first.')]);
        }
    }
}
