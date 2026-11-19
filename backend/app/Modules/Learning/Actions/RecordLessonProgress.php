<?php

namespace App\Modules\Learning\Actions;

use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Modules\Catalog\Services\CourseProgress;
use Illuminate\Support\Facades\DB;

/**
 * Starting and completing lessons.
 *
 * Completion rule (Phase 3): the learner reached the end of the lesson.
 * Phase 5 adds: and has a passing attempt when the lesson has an exercise.
 */
final class RecordLessonProgress
{
    /** Time reported per completion is capped, so a tab left open overnight does not count as study. */
    public const MAX_SECONDS_PER_VISIT = 4 * 60 * 60;

    public function __construct(private readonly CourseProgress $courseProgress) {}

    public function start(User $user, Lesson $lesson): LessonProgress
    {
        return LessonProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['status' => LessonProgressStatus::Started, 'started_at' => now()],
        );
    }

    public function complete(User $user, Lesson $lesson, int $secondsSpent): LessonProgress
    {
        return DB::transaction(function () use ($user, $lesson, $secondsSpent) {
            $progress = LessonProgress::query()->lockForUpdate()->firstOrNew(
                ['user_id' => $user->id, 'lesson_id' => $lesson->id],
                ['started_at' => now(), 'time_spent_seconds' => 0],
            );

            $progress->time_spent_seconds += min(max($secondsSpent, 0), self::MAX_SECONDS_PER_VISIT);
            if ($progress->status !== LessonProgressStatus::Completed) {
                $progress->status = LessonProgressStatus::Completed;
                $progress->completed_at = now();
            }
            $progress->save();

            $this->completeCourseIfFinished($user, $lesson);

            return $progress;
        });
    }

    /** Stamps the enrollment once, the first time every lesson in the course is complete. */
    private function completeCourseIfFinished(User $user, Lesson $lesson): void
    {
        $course = $lesson->chapter->course;
        $enrollment = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNull('completed_at')
            ->first();

        if ($enrollment && $this->courseProgress->for($user, $course)['percent'] === 100) {
            $enrollment->update(['completed_at' => now()]);
        }
    }
}
