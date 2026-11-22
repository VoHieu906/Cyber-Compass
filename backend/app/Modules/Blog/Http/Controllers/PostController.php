<?php

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Modules\Blog\Http\Requests\PostIndexRequest;
use App\Modules\Blog\Http\Resources\PostResource;
use App\Modules\Blog\Http\Resources\PostSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PostController extends Controller
{
    public const DEFAULT_PER_PAGE = 12;

    /**
     * List blog posts.
     *
     * Published posts, newest first, without their sections. Filter by `tag`. `tags` lists every
     * tag in use, for the filter.
     *
     * @unauthenticated
     */
    public function index(PostIndexRequest $request): AnonymousResourceCollection
    {
        $tag = $request->validated('tag');
        $posts = Post::query()
            ->visibleToReaders()
            ->with(['coverMedia', 'author:id,name'])
            ->when($tag, fn ($q) => $q->whereJsonContains('tags', $tag))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate($request->integer('per_page', self::DEFAULT_PER_PAGE))
            ->withQueryString();

        // shortcut: tags are read from every visible post (one small column); add a tags table past a few hundred posts.
        $tags = Post::query()->visibleToReaders()->pluck('tags')->flatten()->unique()->sort()->values();

        return PostSummaryResource::collection($posts)->additional(['tags' => $tags]);
    }

    /**
     * Read a blog post.
     *
     * The post with its sections, rendered by the same blocks as lessons. 404 for drafts and
     * archived posts.
     *
     * @unauthenticated
     */
    public function show(string $slug): PostResource
    {
        $post = Post::query()
            ->visibleToReaders()
            ->where('slug', $slug)
            ->with(['sections', 'coverMedia', 'author:id,name'])
            ->firstOrFail();

        return new PostResource($post);
    }
}
