<?php

namespace App\Modules\Notifications\Listeners;

use App\Enums\NotificationType;
use App\Enums\ScoreBand;
use App\Modules\Assessment\Events\AssessmentSubmitted;
use App\Modules\Content\Events\LessonPublished;
use App\Modules\Learning\Events\LessonCompleted;
use App\Modules\Notifications\Jobs\RecommendNewLesson;
use App\Modules\Notifications\Notifications\LearningNotification;
use App\Modules\Progress\Events\MilestoneReached;

/**
 * Turns learning events into notifications (D34). Registered in AppServiceProvider. The events
 * arrive after commit; the notifications themselves are queued.
 */
final class NotifyLearner
{
    public function lessonCompleted(LessonCompleted $event): void
    {
        if (! $event->firstTime) {
            return;
        }
        $lesson = $event->lesson;
        $event->user->notify(new LearningNotification(NotificationType::LessonCompleted, ['lesson' => $lesson->title, 'slug' => $lesson->slug], '/dashboard'));
    }

    public function assessmentSubmitted(AssessmentSubmitted $event): void
    {
        $attempt = $event->attempt;
        $score = (int) $attempt->score_percent;
        $attempt->user()->firstOrFail()->notify(new LearningNotification(
            NotificationType::AssessmentResult,
            ['score' => $score, 'band' => ScoreBand::forPercent($score)->value, 'attempt' => $attempt->id],
            "/assessment/attempts/{$attempt->id}/result",
        ));
    }

    public function milestoneReached(MilestoneReached $event): void
    {
        $event->user->notify(new LearningNotification(NotificationType::Milestone, $event->params, $event->url));
    }

    public function lessonPublished(LessonPublished $event): void
    {
        RecommendNewLesson::dispatch($event->lesson->id);
    }
}
