<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonRevision;
use App\Modules\Content\Actions\LessonLifecycle;
use App\Modules\Content\Http\Requests\LockVersionRequest;
use App\Modules\Content\Http\Resources\AdminLessonResource;
use App\Modules\Content\Http\Resources\RevisionResource;
use App\Modules\Content\Services\LessonDirectory;
use App\Modules\Content\Services\RevisionSummary;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class LessonRevisionController extends Controller
{
    /**
     * Published versions.
     *
     * Newest first, with author, note and what changed since the version before.
     */
    public function index(Lesson $lesson, RevisionSummary $summary): AnonymousResourceCollection
    {
        Gate::authorize('view', $lesson);

        $revisions = $lesson->revisions()->with('author:id,name')->get();
        $summaries = $summary->forAll($revisions);
        $revisions->each(fn (LessonRevision $r) => $r->setAttribute('summary', $summaries[$r->version]));

        return RevisionResource::collection($revisions);
    }

    /**
     * Restore a version into the draft.
     *
     * The live lesson is untouched until the draft is published. 409 if `lock_version` is stale.
     */
    public function restore(LockVersionRequest $request, Lesson $lesson, int $version, LessonLifecycle $lifecycle): AdminLessonResource
    {
        Gate::authorize('update', $lesson);

        $revision = $lesson->revisions()->where('version', $version)->firstOrFail();
        $lifecycle->restore($request->user(), $lesson, $revision, $request->integer('lock_version'));

        return new AdminLessonResource(LessonDirectory::forEditor($lesson));
    }
}
