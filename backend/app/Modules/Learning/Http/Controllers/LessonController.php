<?php

namespace App\Modules\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Modules\Catalog\Services\CourseProgress;
use App\Modules\Learning\Actions\RecordLessonProgress;
use App\Modules\Learning\Http\Resources\LessonResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LessonController extends Controller
{
    /**
     * Read a lesson.
     *
     * Sections, objectives, takeaways, resources, the previous and next lesson in the
     * course, and my progress. Any signed-in learner can read any published lesson.
     */
    public function show(Request $request, string $slug, CourseProgress $courseProgress): LessonResource
    {
        $lesson = $this->lesson($slug)->load(['sections', 'chapter.course.domain', 'chapter.course.prerequisites:id,slug,title']);

        $ordered = $courseProgress->orderedLessons($lesson->chapter->course)->values();
        $index = $ordered->search(fn (Lesson $l) => $l->id === $lesson->id);
        $link = fn (?Lesson $l) => $l ? ['slug' => $l->slug, 'title' => $l->title] : null;

        $lesson->setRelation('navigation', [
            'position' => $index + 1,
            'total' => $ordered->count(),
            'previous' => $link($ordered->get($index - 1)),
            'next' => $link($ordered->get($index + 1)),
        ]);
        $lesson->setRelation('viewerProgress', $request->user()->lessonProgress()->where('lesson_id', $lesson->id)->first());

        return new LessonResource($lesson);
    }

    /** Mark a lesson as started. Idempotent. */
    public function start(Request $request, string $slug, RecordLessonProgress $record): JsonResponse
    {
        $progress = $record->start($request->user(), $this->lesson($slug));

        return response()->json(['data' => [
            'status' => $progress->status->value,
            'completed_at' => $progress->completed_at?->toIso8601String(),
        ]]);
    }

    /**
     * Mark a lesson as read to the end.
     *
     * `time_spent_seconds` is added to the lesson's study time (capped at 4 hours per call).
     * Returns the lesson status and my updated progress in the course.
     */
    public function complete(Request $request, string $slug, RecordLessonProgress $record, CourseProgress $courseProgress): JsonResponse
    {
        $validated = $request->validate(['time_spent_seconds' => ['required', 'integer', 'min:0', 'max:86400']]);
        $lesson = $this->lesson($slug);
        $progress = $record->complete($request->user(), $lesson, $validated['time_spent_seconds']);

        return response()->json(['data' => [
            'status' => $progress->status->value,
            'completed_at' => $progress->completed_at?->toIso8601String(),
            'course' => $courseProgress->for($request->user(), $lesson->chapter->course),
        ]]);
    }

    /** Lessons of unpublished courses do not exist as far as learners are concerned. */
    private function lesson(string $slug): Lesson
    {
        return Lesson::query()
            ->where('slug', $slug)
            ->whereHas('chapter.course', fn (Builder $q) => $q->where('is_published', true))
            ->with('chapter.course')
            ->firstOrFail();
    }
}
