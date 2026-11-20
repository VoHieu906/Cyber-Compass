<?php

namespace App\Modules\Content\Policies;

use App\Models\Lesson;
use App\Models\User;

/**
 * Who may author content. One admin role today (assumption A1); a method per action so an
 * editor/reviewer split later changes only this class.
 */
final class LessonPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, Lesson $lesson): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, Lesson $lesson): bool
    {
        return $actor->isAdmin();
    }

    public function publish(User $actor, Lesson $lesson): bool
    {
        return $actor->isAdmin();
    }

    public function archive(User $actor, Lesson $lesson): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, Lesson $lesson): bool
    {
        return $actor->isAdmin();
    }

    public function manageStructure(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function uploadMedia(User $actor): bool
    {
        return $actor->isAdmin();
    }
}
