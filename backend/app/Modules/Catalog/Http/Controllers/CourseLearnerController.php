<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Modules\Catalog\Actions\EnrollInCourse;
use App\Modules\Catalog\Services\CourseProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in learner's relationship with one course: progress, enrollment, favorite. */
final class CourseLearnerController extends Controller
{
    /** My progress in a course, with the status of each lesson and the next lesson to take. */
    public function progress(Request $request, string $slug, CourseProgress $progress): JsonResponse
    {
        return response()->json(['data' => $progress->for($request->user(), $this->course($slug))]);
    }

    /** Enroll. Idempotent. */
    public function enroll(Request $request, string $slug, EnrollInCourse $enroll, CourseProgress $progress): JsonResponse
    {
        $course = $this->course($slug);
        $enrollment = $enroll->handle($request->user(), $course);

        return response()->json(
            ['data' => $progress->for($request->user(), $course)],
            $enrollment->wasRecentlyCreated ? 201 : 200,
        );
    }

    /** Add to favorites. Idempotent. */
    public function favorite(Request $request, string $slug): JsonResponse
    {
        $request->user()->favoriteCourses()->syncWithoutDetaching([$this->course($slug)->id]);

        return response()->json(['data' => ['favorited' => true]]);
    }

    /** Remove from favorites. Idempotent. */
    public function unfavorite(Request $request, string $slug): JsonResponse
    {
        $request->user()->favoriteCourses()->detach($this->course($slug)->id);

        return response()->json(['data' => ['favorited' => false]]);
    }

    private function course(string $slug): Course
    {
        return Course::query()->published()->where('slug', $slug)->firstOrFail();
    }
}
