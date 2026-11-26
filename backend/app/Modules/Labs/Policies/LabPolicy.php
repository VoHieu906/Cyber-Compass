<?php

namespace App\Modules\Labs\Policies;

use App\Models\Lab;
use App\Models\User;

/**
 * Who may manage labs. One admin role today (assumption A1); a method per action so an
 * editor and reviewer split later changes only this class.
 */
final class LabPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, Lab $lab): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, Lab $lab): bool
    {
        return $actor->isAdmin();
    }

    public function publish(User $actor, Lab $lab): bool
    {
        return $actor->isAdmin();
    }

    public function archive(User $actor, Lab $lab): bool
    {
        return $actor->isAdmin();
    }

    public function reorder(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function preview(User $actor, Lab $lab): bool
    {
        return $actor->isAdmin();
    }
}
