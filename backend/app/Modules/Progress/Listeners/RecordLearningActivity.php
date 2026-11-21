<?php

namespace App\Modules\Progress\Listeners;

use App\Enums\ActivityType;
use App\Modules\Assessment\Events\AssessmentSubmitted;
use App\Modules\Labs\Events\LabCompleted;
use App\Modules\Learning\Events\ExerciseSubmitted;
use App\Modules\Learning\Events\LessonCompleted;
use App\Modules\Learning\Events\LessonStarted;
use App\Modules\Progress\Services\ActivityRecorder;

/**
 * Turns the learning events into activity rows (D33). The events are dispatched after commit,
 * so a rolled-back action never leaves an activity behind. Registered in AppServiceProvider.
 */
final class RecordLearningActivity
{
    public function __construct(private readonly ActivityRecorder $recorder) {}

    public function lessonStarted(LessonStarted $event): void
    {
        $this->recorder->record($event->user->id, ActivityType::LessonStarted, $event->lesson, now());
    }

    public function lessonCompleted(LessonCompleted $event): void
    {
        $type = $event->firstTime ? ActivityType::LessonCompleted : ActivityType::StudySession;
        $this->recorder->record($event->user->id, $type, $event->lesson, now(), $event->seconds);
    }

    public function exerciseSubmitted(ExerciseSubmitted $event): void
    {
        $attempt = $event->attempt;
        $this->recorder->record($attempt->user_id, ActivityType::ExerciseSubmitted, $attempt, $attempt->submitted_at ?? now(), (int) $attempt->time_spent_seconds);
    }

    public function assessmentSubmitted(AssessmentSubmitted $event): void
    {
        $attempt = $event->attempt;
        $this->recorder->record($attempt->user_id, ActivityType::AssessmentSubmitted, $attempt, $attempt->submitted_at ?? now());
    }

    public function labCompleted(LabCompleted $event): void
    {
        $attempt = $event->attempt;
        $this->recorder->record($attempt->user_id, ActivityType::LabCompleted, $attempt->lab()->firstOrFail(), $attempt->completed_at ?? now());
    }
}
