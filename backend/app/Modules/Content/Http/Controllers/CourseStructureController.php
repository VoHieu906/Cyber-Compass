<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Lesson;
use App\Modules\Content\Actions\CourseStructure;
use App\Modules\Content\Http\Requests\ChapterRequest;
use App\Modules\Content\Http\Requests\ReorderRequest;
use App\Modules\Content\Http\Resources\AdminCourseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class CourseStructureController extends Controller
{
    public function __construct(private readonly CourseStructure $structure) {}

    /**
     * Courses with their chapters and lessons.
     *
     * Every course, chapter and lesson (any status), in order. Feeds the course page and pickers.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lesson::class);

        return AdminCourseResource::collection(self::structureQuery()->orderBy('title')->get());
    }

    /** Add a chapter at the end of a course. */
    public function storeChapter(ChapterRequest $request, Course $course): AdminCourseResource
    {
        Gate::authorize('manageStructure', Lesson::class);
        $this->structure->createChapter($request->user(), $course, trim($request->string('title')->toString()));

        return self::course($course->id);
    }

    /** Rename a chapter. */
    public function updateChapter(ChapterRequest $request, CourseChapter $chapter): AdminCourseResource
    {
        Gate::authorize('manageStructure', Lesson::class);
        $this->structure->renameChapter($request->user(), $chapter, trim($request->string('title')->toString()));

        return self::course($chapter->course_id);
    }

    /** Reorder a course's chapters. Send every chapter id once, in the new order. */
    public function reorderChapters(ReorderRequest $request, Course $course): AdminCourseResource
    {
        Gate::authorize('manageStructure', Lesson::class);
        $this->structure->reorderChapters($request->user(), $course, $request->input('chapter_ids'));

        return self::course($course->id);
    }

    /** Reorder a chapter's lessons. Send every lesson id once (any status), in the new order. */
    public function reorderLessons(ReorderRequest $request, CourseChapter $chapter): AdminCourseResource
    {
        Gate::authorize('manageStructure', Lesson::class);
        $this->structure->reorderLessons($request->user(), $chapter, $request->input('lesson_ids'));

        return self::course($chapter->course_id);
    }

    /** @return Builder<Course> */
    private static function structureQuery(): Builder
    {
        return Course::query()->with([
            'chapters' => fn ($q) => $q->orderBy('position'),
            'chapters.lessons' => fn ($q) => $q->withExists('draft'),
        ]);
    }

    private static function course(int $id): AdminCourseResource
    {
        return new AdminCourseResource(self::structureQuery()->findOrFail($id));
    }
}
