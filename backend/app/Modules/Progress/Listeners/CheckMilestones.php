<?php

namespace App\Modules\Progress\Listeners;

use App\Modules\Assessment\Events\AssessmentSubmitted;
use App\Modules\Labs\Events\LabCompleted;
use App\Modules\Learning\Events\ExerciseSubmitted;
use App\Modules\Learning\Events\LessonCompleted;
use App\Modules\Learning\Events\LessonStarted;
use App\Modules\Progress\Services\MilestoneDetector;

/**
 * Looks for milestones after each learning event (D34). Registered after
 * `RecordLearningActivity`, so the activity that may complete a streak is already stored.
 */
final class CheckMilestones
{
    public function __construct(private readonly MilestoneDetector $detector) {}

    public function handle(LessonStarted|LessonCompleted|ExerciseSubmitted|AssessmentSubmitted|LabCompleted $event): void
    {
        $user = match (true) {
            $event instanceof LessonStarted, $event instanceof LessonCompleted => $event->user,
            default => $event->attempt->user()->firstOrFail(),
        };

        if ($event instanceof LessonCompleted && $event->firstTime) {
            $this->detector->lessonCompleted($user, $event->lesson);
        }
        $this->detector->activity($user);
    }
}
