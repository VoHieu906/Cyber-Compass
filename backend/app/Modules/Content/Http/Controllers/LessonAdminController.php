<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CourseChapter;
use App\Models\Lesson;
use App\Modules\Content\Actions\LessonLifecycle;
use App\Modules\Content\Actions\PublishLesson;
use App\Modules\Content\Actions\SaveDraft;
use App\Modules\Content\Http\Requests\CreateLessonRequest;
use App\Modules\Content\Http\Requests\LessonIndexRequest;
use App\Modules\Content\Http\Requests\LockVersionRequest;
use App\Modules\Content\Http\Resources\AdminLessonListResource;
use App\Modules\Content\Http\Resources\AdminLessonResource;
use App\Modules\Content\Services\LessonDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class LessonAdminController extends Controller
{
    public function __construct(private readonly LessonLifecycle $lifecycle) {}

    /**
     * List lessons for editing.
     *
     * Every status. Search title and slug (`q`); filter by `filter[course]`, `filter[chapter]`,
     * `filter[status]`, `filter[has_draft]`; sort `-updated_at` (default), `title` or `course` (course order).
     */
    public function index(LessonIndexRequest $request, LessonDirectory $directory): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lesson::class);

        return AdminLessonListResource::collection($directory->search($request->validated()));
    }

    /**
     * Create a lesson.
     *
     * Created as a draft at the end of the chapter. Learners never see it until it is published.
     */
    public function store(CreateLessonRequest $request): JsonResponse
    {
        Gate::authorize('create', Lesson::class);

        $lesson = $this->lifecycle->create(
            $request->user(),
            CourseChapter::query()->findOrFail($request->integer('chapter_id')),
            trim($request->string('title')->toString()),
            $request->string('slug')->toString(),
        );

        return (new AdminLessonResource(LessonDirectory::forEditor($lesson)))->response()->setStatusCode(201);
    }

    /**
     * Lesson for editing.
     *
     * The working copy (draft if one exists, otherwise live), the preview sections exactly as
     * learners receive them, and `lock_version` to send back with any change.
     */
    public function show(Lesson $lesson): AdminLessonResource
    {
        Gate::authorize('view', $lesson);

        return new AdminLessonResource(LessonDirectory::forEditor($lesson));
    }

    /**
     * Save the draft.
     *
     * The whole working copy: metadata plus the ordered `sections` ({id?, type, heading, payload}).
     * Sections without an id get a new UUID. 409 `conflict` if `lock_version` is stale; 422 errors
     * are keyed like the body (`sections.3.payload.items.0`).
     */
    public function saveDraft(LockVersionRequest $request, Lesson $lesson, SaveDraft $save): AdminLessonResource
    {
        Gate::authorize('update', $lesson);

        $save->handle($request->user(), $lesson, $request->except(['lock_version', 'note']), $request->integer('lock_version'));

        return new AdminLessonResource(LessonDirectory::forEditor($lesson));
    }

    /** Discard unpublished changes. 422 for a lesson that was never published (delete it instead). */
    public function discardDraft(Request $request, Lesson $lesson): AdminLessonResource
    {
        Gate::authorize('update', $lesson);

        $this->lifecycle->discardDraft($request->user(), $lesson);

        return new AdminLessonResource(LessonDirectory::forEditor($lesson));
    }

    /**
     * Publish.
     *
     * Makes the working copy live, writes revision n+1 with the optional `note`, and drops the draft.
     * Also republishes an archived lesson. 409 if `lock_version` is stale.
     */
    public function publish(LockVersionRequest $request, Lesson $lesson, PublishLesson $publish): AdminLessonResource
    {
        Gate::authorize('publish', $lesson);

        $note = trim((string) $request->input('note')) ?: null;
        $publish->handle($request->user(), $lesson, $request->integer('lock_version'), $note);

        return new AdminLessonResource(LessonDirectory::forEditor($lesson->refresh()));
    }

    /** Archive: hide a published lesson from learners. Progress and links are kept. */
    public function archive(Request $request, Lesson $lesson): AdminLessonResource
    {
        Gate::authorize('archive', $lesson);

        $this->lifecycle->archive($request->user(), $lesson);

        return new AdminLessonResource(LessonDirectory::forEditor($lesson));
    }

    /** Delete a lesson that was never published and has no progress or roadmap links. Otherwise 409. */
    public function destroy(Request $request, Lesson $lesson): Response
    {
        Gate::authorize('delete', $lesson);

        $this->lifecycle->delete($request->user(), $lesson);

        return response()->noContent();
    }
}
