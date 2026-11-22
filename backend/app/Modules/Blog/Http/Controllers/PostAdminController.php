<?php

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Modules\Blog\Actions\PostLifecycle;
use App\Modules\Blog\Http\Requests\AdminPostIndexRequest;
use App\Modules\Blog\Http\Requests\CreatePostRequest;
use App\Modules\Blog\Http\Resources\AdminPostListResource;
use App\Modules\Blog\Http\Resources\AdminPostResource;
use App\Modules\Content\Http\Requests\LockVersionRequest;
use App\Support\Search\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Post authoring (F2). Posts are addressed by id here; readers use slugs. */
final class PostAdminController extends Controller
{
    public function __construct(private readonly PostLifecycle $lifecycle) {}

    /**
     * List posts for editing.
     *
     * Every status, last edited first. Search title and slug (`q`); filter by `filter[status]`
     * and `filter[has_draft]`.
     */
    public function index(AdminPostIndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Post::class);
        $filter = $request->validated('filter') ?? [];

        $query = Post::query()
            ->with(['draft.editor:id,name', 'editor:id,name'])
            ->withCount('sections')
            ->when(trim((string) $request->validated('q')), fn ($q, $term) => Like::anyColumn($q, ['posts.title', 'posts.slug'], $term))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        if (isset($filter['has_draft'])) {
            filter_var($filter['has_draft'], FILTER_VALIDATE_BOOLEAN) ? $query->has('draft') : $query->doesntHave('draft');
        }

        return AdminPostListResource::collection(
            $query->orderByDesc('updated_at')->orderBy('id')->paginate($request->integer('per_page', 20))->withQueryString(),
        );
    }

    /** Create a post. Created as a draft that readers never see until it is published. */
    public function store(CreatePostRequest $request): JsonResponse
    {
        Gate::authorize('create', Post::class);

        $post = $this->lifecycle->create($request->user(), trim($request->string('title')->toString()), $request->string('slug')->toString());

        return (new AdminPostResource(self::forEditor($post)))->response()->setStatusCode(201);
    }

    /**
     * Post for editing.
     *
     * The working copy (draft if one exists, otherwise live), the preview sections exactly as
     * readers receive them, and `lock_version` to send back with any change.
     */
    public function show(Post $post): AdminPostResource
    {
        Gate::authorize('view', $post);

        return new AdminPostResource(self::forEditor($post));
    }

    /**
     * Save the draft.
     *
     * The whole working copy: `title`, `slug`, `summary`, `cover` (media id), `tags`, and the ordered
     * `sections` ({id?, type, heading, payload}, at most 150). The reading time is computed.
     * 409 `conflict` if `lock_version` is stale; 422 errors are keyed like the body.
     */
    public function saveDraft(LockVersionRequest $request, Post $post): AdminPostResource
    {
        Gate::authorize('update', $post);

        $this->lifecycle->saveDraft($request->user(), $post, $request->except(['lock_version', 'note']), $request->integer('lock_version'));

        return new AdminPostResource(self::forEditor($post));
    }

    /** Discard unpublished changes. 422 for a post that was never published (delete it instead). */
    public function discardDraft(Request $request, Post $post): AdminPostResource
    {
        Gate::authorize('update', $post);

        $this->lifecycle->discardDraft($request->user(), $post);

        return new AdminPostResource(self::forEditor($post));
    }

    /** Publish: makes the working copy live, writes revision n+1 with the optional `note`. 409 if `lock_version` is stale. */
    public function publish(LockVersionRequest $request, Post $post): AdminPostResource
    {
        Gate::authorize('publish', $post);

        $note = trim((string) $request->input('note')) ?: null;
        $this->lifecycle->publish($request->user(), $post, $request->integer('lock_version'), $note);

        return new AdminPostResource(self::forEditor($post->refresh()));
    }

    /** Archive: hide a published post from readers. */
    public function archive(Request $request, Post $post): AdminPostResource
    {
        Gate::authorize('archive', $post);

        $this->lifecycle->archive($request->user(), $post);

        return new AdminPostResource(self::forEditor($post));
    }

    /** Unarchive: show an archived post to readers again, as it was. */
    public function unarchive(Request $request, Post $post): AdminPostResource
    {
        Gate::authorize('archive', $post);

        $this->lifecycle->unarchive($request->user(), $post);

        return new AdminPostResource(self::forEditor($post));
    }

    /** Delete a post that was never published. Otherwise 409 (archive it). */
    public function destroy(Request $request, Post $post): Response
    {
        Gate::authorize('delete', $post);

        $this->lifecycle->delete($request->user(), $post);

        return response()->noContent();
    }

    public static function forEditor(Post $post): Post
    {
        return $post->load(['draft.editor:id,name']);
    }
}
