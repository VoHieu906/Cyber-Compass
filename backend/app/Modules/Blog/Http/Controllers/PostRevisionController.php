<?php

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostRevision;
use App\Modules\Blog\Actions\PostLifecycle;
use App\Modules\Blog\Http\Resources\AdminPostResource;
use App\Modules\Content\Http\Requests\LockVersionRequest;
use App\Modules\Content\Http\Resources\RevisionResource;
use App\Modules\Content\Services\RevisionSummary;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class PostRevisionController extends Controller
{
    /** Published versions of a post, newest first, with what changed since the version before. */
    public function index(Post $post, RevisionSummary $summary): AnonymousResourceCollection
    {
        Gate::authorize('view', $post);

        $revisions = $post->revisions()->with('author:id,name')->get();
        $summaries = $summary->forAll($revisions);
        $revisions->each(fn (PostRevision $r) => $r->setAttribute('summary', $summaries[$r->version]));

        return RevisionResource::collection($revisions);
    }

    /** Restore a version into the draft. The live post is untouched until it is published. 409 if `lock_version` is stale. */
    public function restore(LockVersionRequest $request, Post $post, int $version, PostLifecycle $lifecycle): AdminPostResource
    {
        Gate::authorize('update', $post);

        $revision = $post->revisions()->where('version', $version)->firstOrFail();
        $lifecycle->restore($request->user(), $post, $revision, $request->integer('lock_version'));

        return new AdminPostResource(PostAdminController::forEditor($post));
    }
}
