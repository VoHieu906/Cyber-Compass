<?php

namespace App\Modules\Learning\Events;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A learner opened a lesson for the first time. Dispatched after commit (D33). */
final class LessonStarted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Lesson $lesson,
    ) {}
}
