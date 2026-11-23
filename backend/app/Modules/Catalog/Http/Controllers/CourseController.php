<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Modules\Catalog\Http\Requests\CourseIndexRequest;
use App\Modules\Catalog\Http\Resources\CourseDetailResource;
use App\Modules\Catalog\Http\Resources\CourseSummaryResource;
use App\Modules\Catalog\Services\CourseCatalog;
use App\Modules\Catalog\Services\RelatedCourses;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CourseController extends Controller
{
    /**
     * List courses.
     *
     * Search (`q`), filter by `filter[domain]`, `filter[difficulty]` and, when signed in,
     * `filter[favorited]`; sort with `sort`. Signed-in viewers also get `is_favorited` per course.
     *
     * @unauthenticated
     */
    public function index(CourseIndexRequest $request, CourseCatalog $catalog): AnonymousResourceCollection
    {
        return CourseSummaryResource::collection(
            $catalog->search($request->validated(), $request->user('sanctum')),
        );
    }

    /**
     * Course detail.
     *
     * Overview, objectives, skills, prerequisites, chapters with lessons, related courses, and the
     * course map (`graph`, null when the course has none; F3).
     *
     * @unauthenticated
     */
    public function show(string $slug, RelatedCourses $related): CourseDetailResource
    {
        $course = Course::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'domain',
                'skills',
                'prerequisites:id,slug,title',
                'chapters.lessons' => fn ($q) => $q->published(),
                'chapters.lessons.architecture',
                'graph',
            ])
            ->withLessonStats()
            ->firstOrFail();

        $course->setRelation('related', $related->for($course));

        return new CourseDetailResource($course);
    }
}
