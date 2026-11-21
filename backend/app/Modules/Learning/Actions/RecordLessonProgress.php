<?php

namespace App\Modules\Learning\Actions;

use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Modules\Catalog\Services\CourseProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starting and completing lessons.
 *
 * Completion rule (ARCHITECTURE §6.3, D31): the learner reached the end of the lesson and, when
 * the lesson has an exercise, has a passing attempt. A passing attempt completes the lesson
 * through this class (`SubmitExercise`). Completions recorded before an exercise existed stay.
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

            if ($progress->status !== LessonProgressStatus::Completed && $this->exerciseBlocks($user, $lesson)) {
                throw ValidationException::withMessages(['exercise' => [__('Pass the lesson exercise to complete this lesson.')]]);
            }

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

    /** The lesson has an exercise with questions, and the learner has not passed it. */
    private function exerciseBlocks(User $user, Lesson $lesson): bool
    {
        $exercise = Exercise::query()->where('lesson_id', $lesson->id)->whereHas('questions')->first();

        return $exercise !== null && ! $user->exerciseAttempts()->where('exercise_id', $exercise->id)->where('passed', true)->exists();
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
