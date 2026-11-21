<?php

namespace App\Modules\Progress\Console;

use App\Enums\ActivityType;
use App\Models\AssessmentAttempt;
use App\Models\ExerciseAttempt;
use App\Models\LabAttempt;
use App\Models\LessonProgress;
use App\Modules\Progress\Services\ActivityRecorder;
use Illuminate\Console\Command;

/**
 * Fills `learning_activities` from what was recorded before Phase 6 (D33): lesson starts and
 * completions, exercise and assessment submissions, and lab completions. Safe to run again:
 * an activity already present for the same learner, type and subject is skipped.
 *
 * Lesson time stored before Phase 6 includes the time of the exercise that completed the
 * lesson, so that part is taken off the lesson's row and counted once, on the exercise.
 */
final class BackfillActivitiesCommand extends Command
{
    protected $signature = 'progress:backfill';

    protected $description = 'Record learning activities for progress made before activity recording existed';

    public function handle(ActivityRecorder $recorder): int
    {
        $added = 0;

        LessonProgress::query()->with('lesson:id')->chunkById(500, function ($rows) use ($recorder, &$added) {
            foreach ($rows as $progress) {
                $added += (int) $recorder->once($progress->user_id, ActivityType::LessonStarted, $progress->lesson, $progress->started_at);
                if ($progress->completed_at) {
                    $exerciseSeconds = (int) ExerciseAttempt::query()
                        ->where('user_id', $progress->user_id)
                        ->whereHas('exercise', fn ($q) => $q->where('lesson_id', $progress->lesson_id))
                        ->sum('time_spent_seconds');
                    $seconds = max(0, $progress->time_spent_seconds - $exerciseSeconds);
                    $added += (int) $recorder->once($progress->user_id, ActivityType::LessonCompleted, $progress->lesson, $progress->completed_at, $seconds);
                }
            }
        });

        ExerciseAttempt::query()->chunkById(500, function ($rows) use ($recorder, &$added) {
            foreach ($rows as $attempt) {
                $added += (int) $recorder->once($attempt->user_id, ActivityType::ExerciseSubmitted, $attempt, $attempt->submitted_at, (int) $attempt->time_spent_seconds);
            }
        });

        AssessmentAttempt::query()->submitted()->chunkById(500, function ($rows) use ($recorder, &$added) {
            foreach ($rows as $attempt) {
                $added += (int) $recorder->once($attempt->user_id, ActivityType::AssessmentSubmitted, $attempt, $attempt->submitted_at);
            }
        });

        LabAttempt::query()->whereNotNull('completed_at')->with('lab:id')->chunkById(500, function ($rows) use ($recorder, &$added) {
            foreach ($rows as $attempt) {
                $added += (int) $recorder->once($attempt->user_id, ActivityType::LabCompleted, $attempt->lab, $attempt->completed_at);
            }
        });

        $this->info("Recorded {$added} learning activities.");

        return self::SUCCESS;
    }
}
