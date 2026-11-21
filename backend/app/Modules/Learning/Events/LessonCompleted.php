<?php

namespace App\Modules\Learning\Events;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A learner reached the end of a lesson, from `RecordLessonProgress::complete()`, the one place
 * lessons complete (including through a passing exercise). Dispatched after commit (D33).
 *
 * `firstTime` is false for a review of a lesson already completed. `seconds` is the study time
 * this visit adds; it is 0 when the completion came from an exercise, whose attempt carries the
 * time itself, so nothing is counted twice.
 */
final class LessonCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Lesson $lesson,
        public readonly int $seconds,
        public readonly bool $firstTime,
    ) {}
}
