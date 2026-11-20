<?php

namespace App\Modules\Catalog\Services;

use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A learner's progress through one course, derived from lesson_progress on read.
 * Nothing here is stored, so it can never drift from the underlying records.
 */
final class CourseProgress
{
    /**
     * @return array{
     *     enrolled: bool, enrolled_at: ?string, completed_at: ?string, favorited: bool,
     *     completed_lessons: int, total_lessons: int, percent: int,
     *     next_lesson: ?array{slug: string, title: string},
     *     lessons: array<string, string>
     * }
     */
    public function for(User $user, Course $course): array
    {
        $lessons = $this->orderedLessons($course);
        $statuses = $user->lessonProgress()
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->pluck('status', 'lesson_id');

        $statusOf = fn (Lesson $l) => ($statuses[$l->id] ?? null)?->value ?? 'not_started';
        $completed = $lessons->filter(fn (Lesson $l) => $statusOf($l) === LessonProgressStatus::Completed->value)->count();
        $next = $lessons->first(fn (Lesson $l) => $statusOf($l) !== LessonProgressStatus::Completed->value);
        $enrollment = $user->enrollments()->where('course_id', $course->id)->first();

        return [
            'enrolled' => $enrollment !== null,
            'enrolled_at' => $enrollment?->enrolled_at?->toIso8601String(),
            'completed_at' => $enrollment?->completed_at?->toIso8601String(),
            'favorited' => $user->favoriteCourses()->whereKey($course->id)->exists(),
            'completed_lessons' => $completed,
            'total_lessons' => $lessons->count(),
            'percent' => $lessons->isEmpty() ? 0 : (int) floor($completed * 100 / $lessons->count()),
            'next_lesson' => $next ? ['slug' => $next->slug, 'title' => $next->title] : null,
            'lessons' => $lessons->mapWithKeys(fn (Lesson $l) => [$l->slug => $statusOf($l)])->all(),
        ];
    }

    /**
     * Published lessons in reading order: chapter position, then lesson position.
     * Drafts and archived lessons never count towards progress or navigation.
     *
     * @return Collection<int, Lesson>
     */
    public function orderedLessons(Course $course): Collection
    {
        return Lesson::query()
            ->select('lessons.*')
            ->join('course_chapters', 'course_chapters.id', '=', 'lessons.course_chapter_id')
            ->where('course_chapters.course_id', $course->id)
            ->orderBy('course_chapters.position')
            ->published()
            ->orderBy('lessons.position')
            ->get();
    }
}
