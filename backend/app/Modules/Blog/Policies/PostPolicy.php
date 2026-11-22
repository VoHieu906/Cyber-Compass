<?php

namespace App\Modules\Blog\Policies;

use App\Models\Post;
use App\Models\User;

/** Who may author posts. One admin role today (assumption A1); a method per action, like LessonPolicy. */
final class PostPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, Post $post): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, Post $post): bool
    {
        return $actor->isAdmin();
    }

    public function publish(User $actor, Post $post): bool
    {
        return $actor->isAdmin();
    }

    public function archive(User $actor, Post $post): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, Post $post): bool
    {
        return $actor->isAdmin();
    }
}
