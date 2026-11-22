<?php

namespace App\Modules\Blog\Actions;

use App\Enums\LessonStatus;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Blog\Services\PostWorkingCopy;
use App\Modules\Content\Documents\DocumentDrafts;
use App\Modules\Content\Documents\DocumentPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Every post action: create, save draft, publish, discard draft, archive, unarchive, delete,
 * restore a revision. Each is one transaction with its audit entry; the draft, publish and
 * restore steps are the shared block-document ones (D55). A post that was ever published is
 * archived, never deleted.
 */
final class PostLifecycle
{
    public function __construct(
        private readonly PostWorkingCopy $copy,
        private readonly DocumentPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    /** A new post: an invisible `draft` row plus its draft. */
    public function create(User $actor, string $title, string $slug): Post
    {
        return DB::transaction(function () use ($actor, $title, $slug) {
            $post = Post::query()->create([
                'slug' => $slug,
                'title' => $title,
                'summary' => '',
                'tags' => [],
                'estimated_minutes' => 1,
                'status' => LessonStatus::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            DocumentDrafts::store($post, null, $this->copy->fromLive($post), $actor);
            $this->audit->record($actor, 'post.created', 'post', $post->id, ['slug' => $slug]);

            return $post;
        });
    }

    /** @param  array<string, mixed>  $input  request body without lock_version */
    public function saveDraft(User $actor, Post $post, array $input, int $lockVersion): void
    {
        DB::transaction(function () use ($actor, $post, $input, $lockVersion) {
            $draft = DocumentDrafts::locked($post);
            DocumentDrafts::assertVersion($post, $draft, $lockVersion);
            DocumentDrafts::store($post, $draft, $this->copy->validate($post, $input), $actor);
        });
    }

    public function publish(User $actor, Post $post, int $lockVersion, ?string $note): PostRevision
    {
        /** @var PostRevision */
        return $this->publisher->publish($actor, $post, $this->copy, $lockVersion, $note, function (array $snapshot) use ($actor, $post) {
            $post->fill([
                'slug' => $snapshot['slug'],
                'title' => $snapshot['title'],
                'summary' => $snapshot['summary'],
                'cover' => $snapshot['cover'],
                'tags' => $snapshot['tags'],
                'estimated_minutes' => $snapshot['estimated_minutes'],
                'status' => LessonStatus::Published,
                'published_at' => $post->published_at ?? now(),
                'updated_by' => $actor->id,
            ])->save();
        });
    }

    /** Drops unpublished changes. A never-published post has nothing to fall back to: delete it instead. */
    public function discardDraft(User $actor, Post $post): void
    {
        if ($post->published_at === null) {
            throw ValidationException::withMessages(['post' => __('This post was never published, so there is nothing to go back to. Delete the post instead.')]);
        }

        $this->publisher->discardDraft($actor, $post);
    }

    /** Hides a published post from readers. Unarchiving shows the same version again. */
    public function archive(User $actor, Post $post): void
    {
        if ($post->status !== LessonStatus::Published) {
            throw ValidationException::withMessages(['post' => __('Only a published post can be archived.')]);
        }

        DB::transaction(function () use ($actor, $post) {
            $post->update(['status' => LessonStatus::Archived, 'updated_by' => $actor->id]);
            $this->audit->record($actor, 'post.archived', 'post', $post->id, ['status' => ['published', 'archived']]);
        });
    }

    public function unarchive(User $actor, Post $post): void
    {
        if ($post->status !== LessonStatus::Archived) {
            throw ValidationException::withMessages(['post' => __('Only an archived post can be brought back.')]);
        }

        DB::transaction(function () use ($actor, $post) {
            $post->update(['status' => LessonStatus::Published, 'updated_by' => $actor->id]);
            $this->audit->record($actor, 'post.unarchived', 'post', $post->id, ['status' => ['archived', 'published']]);
        });
    }

    /** Deletes a post that never reached readers. A published post is archived instead (409). */
    public function delete(User $actor, Post $post): void
    {
        if (! $this->canDelete($post)) {
            throw new ConflictHttpException(__('This post has been published. Archive it instead.'));
        }

        DB::transaction(function () use ($actor, $post) {
            $this->audit->record($actor, 'post.deleted', 'post', $post->id, ['slug' => $post->slug, 'title' => $post->title]);
            $post->delete();
        });
    }

    public function canDelete(Post $post): bool
    {
        return $post->published_at === null;
    }

    public function restore(User $actor, Post $post, PostRevision $revision, int $lockVersion): void
    {
        $this->publisher->restore($actor, $post, $this->copy, $revision, $lockVersion);
    }
}
