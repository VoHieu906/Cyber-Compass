<?php

use App\Enums\LessonStatus;
use App\Models\AdminAuditLog;
use App\Models\LearningActivity;
use App\Models\LessonMedia;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use App\Modules\Blog\Actions\PostLifecycle;
use App\Modules\Content\Events\LessonPublished;
use App\Modules\Learning\Events\LessonStarted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
| Blog posts (F2, ARCHITECTURE D55): authoring on the shared block-document core, one visibility
| scope for readers, search, and the query budgets of long posts.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $this->learner = User::factory()->create();
});

/** @return list<array<string, mixed>> */
function postBlocks(int $prose = 2): array
{
    $blocks = [];
    for ($i = 1; $i <= $prose; $i++) {
        $blocks[] = ['type' => 'prose', 'heading' => "Part {$i}", 'payload' => ['text' => "Containers share the host kernel. Paragraph {$i} explains why that matters for isolation and density."]];
    }
    $blocks[] = ['type' => 'callout', 'heading' => null, 'payload' => ['text' => 'Never mount the Docker socket into a container.', 'tone' => 'warning']];
    $blocks[] = ['type' => 'code', 'heading' => 'Run it', 'payload' => ['code' => "docker run --rm hello-world\ndocker ps -a", 'language' => 'bash']];

    return $blocks;
}

function asAdmin(): mixed
{
    Auth::forgetGuards();

    return test()->actingAs(test()->admin, 'web');
}

/** Creates a post through the API and returns its editor payload. */
function newPost(string $slug = 'containers-explained', string $title = 'Containers explained'): array
{
    return asAdmin()->postJson('/api/v1/admin/content/posts', ['title' => $title, 'slug' => $slug])->assertCreated()->json('data');
}

function saveBody(array $data, array $changes = []): array
{
    return [...$data['working_copy'], 'lock_version' => $data['lock_version'], ...$changes];
}

/** A published post with the given blocks, made through the API. */
function publishedPost(string $slug = 'containers-explained', ?array $blocks = null, array $fields = []): Post
{
    $data = newPost($slug, ucfirst(str_replace('-', ' ', $slug)));
    $data = asAdmin()->putJson("/api/v1/admin/content/posts/{$data['id']}/draft", saveBody($data, [
        'summary' => 'What a container really is.',
        'tags' => ['docker', 'linux'],
        'sections' => $blocks ?? postBlocks(),
        ...$fields,
    ]))->assertOk()->json('data');
    asAdmin()->postJson("/api/v1/admin/content/posts/{$data['id']}/publish", ['lock_version' => $data['lock_version'], 'note' => 'First version'])->assertOk();
    Auth::forgetGuards();

    return Post::query()->findOrFail($data['id']);
}

function guestGet(string $uri): mixed
{
    Auth::forgetGuards();

    return test()->getJson($uri);
}

describe('authoring', function () {
    it('creates an invisible draft with an audit entry', function () {
        $data = newPost();

        expect($data['status'])->toBe('draft')
            ->and($data['lock_version'])->toBe(1)
            ->and($data['slug_editable'])->toBeTrue()
            ->and($data['can_delete'])->toBeTrue()
            ->and($data['working_copy'])->toMatchArray(['slug' => 'containers-explained', 'title' => 'Containers explained', 'tags' => [], 'cover' => null, 'sections' => []]);
        expect(AdminAuditLog::query()->where('action', 'post.created')->where('subject_id', $data['id'])->exists())->toBeTrue();
        guestGet('/api/v1/posts/containers-explained')->assertNotFound();
    });

    it('saves drafts with optimistic locking and computes the reading time', function () {
        $data = newPost();
        $saved = asAdmin()->putJson("/api/v1/admin/content/posts/{$data['id']}/draft", saveBody($data, [
            'summary' => 'Short.', 'tags' => ['docker'], 'sections' => postBlocks(),
        ]))->assertOk()->json('data');

        expect($saved['lock_version'])->toBe(2)
            ->and($saved['working_copy']['estimated_minutes'])->toBeGreaterThanOrEqual(1)
            ->and($saved['working_copy']['sections'])->toHaveCount(4)
            ->and($saved['preview_sections'][2])->toMatchArray(['type' => 'callout']);

        // A typed reading time is ignored: it is always computed.
        $again = asAdmin()->putJson("/api/v1/admin/content/posts/{$data['id']}/draft", saveBody($saved, ['estimated_minutes' => 99]))->assertOk()->json('data');
        expect($again['working_copy']['estimated_minutes'])->toBe($saved['working_copy']['estimated_minutes']);

        // The first tab's version is stale now.
        asAdmin()->putJson("/api/v1/admin/content/posts/{$data['id']}/draft", saveBody($data))
            ->assertConflict()->assertJsonPath('message', 'Someone else changed this post since you opened it. Reload to see their changes, then make yours again.');
    });

    it('validates blocks, tags, the cover and the section limit with body-shaped keys', function () {
        $data = newPost();
        $tooMany = array_fill(0, Post::MAX_SECTIONS + 1, ['type' => 'prose', 'heading' => null, 'payload' => ['text' => 'x']]);

        asAdmin()->putJson("/api/v1/admin/content/posts/{$data['id']}/draft", saveBody($data, [
            'tags' => ['Docker!', 'ok'],
            'cover' => '3f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30',
            'sections' => [['type' => 'callout', 'heading' => null, 'payload' => ['text' => 'Hi', 'tone' => 'loud']]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['tags.0', 'cover', 'sections.0.payload.tone']);

        asAdmin()->putJson("/api/v1/admin/content/posts/{$data['id']}/draft", saveBody($data, ['sections' => $tooMany]))
            ->assertUnprocessable()->assertJsonValidationErrors(['sections']);
    });

    it('accepts a long post of 150 blocks', function () {
        $data = newPost();
        $blocks = array_map(fn ($i) => ['type' => 'prose', 'heading' => "Part {$i}", 'payload' => ['text' => str_repeat('Words that fill a long post. ', 30)]], range(1, Post::MAX_SECTIONS));

        $saved = asAdmin()->putJson("/api/v1/admin/content/posts/{$data['id']}/draft", saveBody($data, ['summary' => 'Long.', 'sections' => $blocks]))->assertOk()->json('data');

        expect($saved['working_copy']['sections'])->toHaveCount(150)
            // 150 x 182 words (6 x 30 + a 2-word heading) = 27 300 words at 200 a minute.
            ->and($saved['working_copy']['estimated_minutes'])->toBe(137);
    });

    it('publishes with a note, writes revisions and keeps section ids stable', function () {
        $post = publishedPost();

        expect($post->status)->toBe(LessonStatus::Published)
            ->and($post->published_at)->not->toBeNull()
            ->and($post->tags)->toBe(['docker', 'linux'])
            ->and($post->sections()->count())->toBe(4)
            ->and(PostRevision::query()->where('post_id', $post->id)->value('note'))->toBe('First version')
            ->and(AdminAuditLog::query()->where('action', 'post.published')->where('subject_id', $post->id)->exists())->toBeTrue();

        $data = asAdmin()->getJson("/api/v1/admin/content/posts/{$post->id}")->assertOk()->json('data');
        expect($data['slug_editable'])->toBeFalse()->and($data['can_delete'])->toBeFalse();
        $ids = array_column($data['working_copy']['sections'], 'id');

        // Reorder and publish again: the same section rows move.
        $sections = array_reverse($data['working_copy']['sections']);
        $saved = asAdmin()->putJson("/api/v1/admin/content/posts/{$post->id}/draft", saveBody($data, ['sections' => $sections, 'title' => 'Containers, explained']))->assertOk()->json('data');
        guestGet('/api/v1/posts/containers-explained')->assertOk()->assertJsonPath('data.title', 'Containers explained');
        asAdmin()->postJson("/api/v1/admin/content/posts/{$post->id}/publish", ['lock_version' => $saved['lock_version']])->assertOk();

        expect($post->sections()->pluck('uuid')->all())->toBe(array_reverse($ids));
        $revisions = asAdmin()->getJson("/api/v1/admin/content/posts/{$post->id}/revisions")->assertOk()->json('data');
        expect($revisions)->toHaveCount(2)
            ->and($revisions[0]['changes'])->toMatchArray(['fields' => ['title'], 'sections' => ['added' => 0, 'removed' => 0, 'edited' => 0, 'reordered' => true]]);
    });

    it('restores a revision into the draft without touching the live post', function () {
        $post = publishedPost();
        $data = asAdmin()->getJson("/api/v1/admin/content/posts/{$post->id}")->json('data');
        $saved = asAdmin()->putJson("/api/v1/admin/content/posts/{$post->id}/draft", saveBody($data, ['sections' => []]))->assertOk()->json('data');

        $restored = asAdmin()->postJson("/api/v1/admin/content/posts/{$post->id}/revisions/1/restore", ['lock_version' => $saved['lock_version']])->assertOk()->json('data');

        expect($restored['working_copy']['sections'])->toHaveCount(4)
            ->and($restored['has_unpublished_changes'])->toBeTrue()
            ->and(AdminAuditLog::query()->where('action', 'post.revision_restored')->exists())->toBeTrue();
        // Publishing an empty post is refused.
        asAdmin()->postJson("/api/v1/admin/content/posts/{$post->id}/revisions/9/restore", ['lock_version' => $restored['lock_version']])->assertNotFound();
    });

    it('refuses to publish without a summary or sections', function () {
        $data = newPost();

        asAdmin()->postJson("/api/v1/admin/content/posts/{$data['id']}/publish", ['lock_version' => $data['lock_version']])
            ->assertUnprocessable()->assertJsonValidationErrors(['summary', 'sections']);
    });

    it('archives and unarchives, and deletes only posts that were never published', function () {
        $post = publishedPost();

        asAdmin()->deleteJson("/api/v1/admin/content/posts/{$post->id}")->assertConflict();
        asAdmin()->postJson("/api/v1/admin/content/posts/{$post->id}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
        guestGet('/api/v1/posts/containers-explained')->assertNotFound();
        asAdmin()->postJson("/api/v1/admin/content/posts/{$post->id}/archive")->assertUnprocessable();
        asAdmin()->postJson("/api/v1/admin/content/posts/{$post->id}/unarchive")->assertOk()->assertJsonPath('data.status', 'published');
        guestGet('/api/v1/posts/containers-explained')->assertOk();

        $draft = newPost('scratch', 'Scratch');
        asAdmin()->deleteJson("/api/v1/admin/content/posts/{$draft['id']}/draft")->assertUnprocessable();
        asAdmin()->deleteJson("/api/v1/admin/content/posts/{$draft['id']}")->assertNoContent();

        expect(AdminAuditLog::query()->whereIn('action', ['post.archived', 'post.unarchived', 'post.deleted'])->count())->toBe(3)
            ->and(Post::query()->whereKey($draft['id'])->exists())->toBeFalse();
    });

    it('discards a draft of a published post', function () {
        $post = publishedPost();
        $data = asAdmin()->getJson("/api/v1/admin/content/posts/{$post->id}")->json('data');
        asAdmin()->putJson("/api/v1/admin/content/posts/{$post->id}/draft", saveBody($data, ['title' => 'Changed']))->assertOk();

        asAdmin()->deleteJson("/api/v1/admin/content/posts/{$post->id}/draft")->assertOk()
            ->assertJsonPath('data.has_unpublished_changes', false)
            ->assertJsonPath('data.working_copy.title', 'Containers explained');
        expect(AdminAuditLog::query()->where('action', 'post.draft_discarded')->exists())->toBeTrue();
    });

    it('lists posts for admins with status and draft filters', function () {
        publishedPost('first-post');
        newPost('second-post', 'Second post');

        $all = asAdmin()->getJson('/api/v1/admin/content/posts')->assertOk()->json('data');
        $drafts = asAdmin()->getJson('/api/v1/admin/content/posts?filter[status]=draft')->json('data');
        $search = asAdmin()->getJson('/api/v1/admin/content/posts?q=first')->json('data');

        expect(array_column($all, 'slug'))->toContain('first-post', 'second-post')
            ->and(array_column($drafts, 'slug'))->toBe(['second-post'])
            ->and(array_column($search, 'slug'))->toBe(['first-post'])
            ->and($all[0])->toHaveKeys(['id', 'title', 'status', 'has_unpublished_changes', 'tags', 'section_count', 'last_edited_by']);
    });

    it('keeps every admin route closed to learners and guests', function () {
        $post = publishedPost();
        $routes = [
            ['getJson', '/api/v1/admin/content/posts'],
            ['postJson', '/api/v1/admin/content/posts'],
            ['getJson', "/api/v1/admin/content/posts/{$post->id}"],
            ['putJson', "/api/v1/admin/content/posts/{$post->id}/draft"],
            ['postJson', "/api/v1/admin/content/posts/{$post->id}/publish"],
            ['postJson', "/api/v1/admin/content/posts/{$post->id}/archive"],
            ['postJson', "/api/v1/admin/content/posts/{$post->id}/unarchive"],
            ['deleteJson', "/api/v1/admin/content/posts/{$post->id}"],
            ['getJson', "/api/v1/admin/content/posts/{$post->id}/revisions"],
            ['postJson', "/api/v1/admin/content/posts/{$post->id}/revisions/1/restore"],
        ];

        foreach ($routes as [$method, $uri]) {
            Auth::forgetGuards();
            $this->actingAs($this->learner, 'web')->{$method}($uri)->assertForbidden();
            Auth::forgetGuards();
            $this->{$method}($uri)->assertUnauthorized();
        }
    });
});

describe('reading', function () {
    it('shows published posts to guests, newest first, without sections in the list', function () {
        $old = publishedPost('older-post');
        $old->forceFill(['published_at' => now()->subWeek()])->save();
        publishedPost('newer-post', null, ['tags' => ['networking']]);
        newPost('unpublished', 'Unpublished');

        $list = guestGet('/api/v1/posts')->assertOk();

        expect(array_column($list->json('data'), 'slug'))->toBe(['newer-post', 'older-post'])
            ->and($list->json('data.0'))->not->toHaveKey('sections')
            ->and($list->json('data.0'))->toHaveKeys(['title', 'summary', 'tags', 'estimated_minutes', 'published_at', 'author', 'cover'])
            ->and($list->json('tags'))->toBe(['docker', 'linux', 'networking'])
            ->and($list->json('meta.total'))->toBe(2);
        expect(array_column(guestGet('/api/v1/posts?tag=networking')->json('data'), 'slug'))->toBe(['newer-post']);

        $post = guestGet('/api/v1/posts/newer-post')->assertOk()->json('data');
        expect($post['sections'])->toHaveCount(4)
            ->and($post['sections'][0])->toHaveKeys(['id', 'type', 'heading', 'payload'])
            ->and($post['author'])->toBe('Ada Admin');
    });

    it('never shows drafts or archived posts through the scope, the list, the detail or search', function () {
        $published = publishedPost('visible-post');
        $archived = publishedPost('archived-post');
        app(PostLifecycle::class)->archive($this->admin, $archived);
        newPost('draft-post', 'Draft post');

        expect(Post::query()->visibleToReaders()->pluck('slug')->all())->toBe(['visible-post']);
        expect(array_column(guestGet('/api/v1/posts')->json('data'), 'slug'))->toBe(['visible-post']);
        guestGet('/api/v1/posts/archived-post')->assertNotFound();
        guestGet('/api/v1/posts/draft-post')->assertNotFound();

        $hits = guestGet('/api/v1/search?q=post')->assertOk()->json('data.posts');
        expect(array_column($hits, 'slug'))->toBe(['visible-post'])
            ->and($hits[0])->toHaveKeys(['slug', 'title', 'summary']);
        expect($published->fresh()->status)->toBe(LessonStatus::Published);
    });

    it('resolves the cover and media blocks to their images', function () {
        $media = LessonMedia::query()->create(['uuid' => '3f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30', 'path' => 'lesson-media/x.webp', 'alt' => 'A diagram', 'width' => 800, 'height' => 400, 'size_bytes' => 10]);
        publishedPost('pictured', [['type' => 'media', 'heading' => null, 'payload' => ['media' => $media->uuid, 'caption' => 'Layers']]], ['cover' => $media->uuid]);

        $post = guestGet('/api/v1/posts/pictured')->json('data');

        expect($post['cover'])->toMatchArray(['id' => $media->uuid, 'alt' => 'A diagram', 'width' => 800])
            ->and($post['sections'][0]['payload'])->toMatchArray(['alt' => 'A diagram', 'width' => 800]);
    });

    it('is never learning: reading a post records no activity and raises no learning event', function () {
        Event::fake();
        $this->actingAs($this->learner, 'web');
        $post = publishedPost();
        $this->actingAs($this->learner, 'web')->getJson("/api/v1/posts/{$post->slug}")->assertOk();

        Event::assertNotDispatched(LessonStarted::class);
        Event::assertNotDispatched(LessonPublished::class);
        expect(LearningActivity::query()->where('user_id', $this->learner->id)->count())->toBe(0);
    });
});

describe('performance', function () {
    it('keeps the list and a 150-block post within their query budgets', function () {
        $blocks = array_map(fn ($i) => $i % 10 === 0
            ? ['type' => 'media', 'heading' => null, 'payload' => ['media' => '3f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30']]
            : ['type' => 'prose', 'heading' => "Part {$i}", 'payload' => ['text' => str_repeat('Long post text. ', 60)]], range(1, Post::MAX_SECTIONS));
        LessonMedia::query()->create(['uuid' => '3f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30', 'path' => 'lesson-media/x.webp', 'alt' => 'A', 'width' => 8, 'height' => 4, 'size_bytes' => 1]);
        foreach (range(1, 5) as $i) {
            publishedPost("long-post-{$i}", $blocks, ['cover' => '3f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30']);
        }
        Auth::forgetGuards();

        $count = function (string $uri) {
            $this->getJson($uri)->assertOk();
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $response = $this->getJson($uri)->assertOk();

            return [$n, strlen($response->getContent())];
        };

        [$listQueries, $listBytes] = $count('/api/v1/posts');
        [$detailQueries, $detailBytes] = $count('/api/v1/posts/long-post-1');

        // Budgets (docs/PERFORMANCE.md): list 7 and detail 7 (5 each measured); the list never
        // carries sections, so its size does not grow with the posts.
        expect($listQueries)->toBeLessThanOrEqual(7)
            ->and($detailQueries)->toBeLessThanOrEqual(7)
            ->and($listBytes)->toBeLessThan(10_000)
            // A 150-block post of about 9 000 words is about 150 KB of JSON (gzip on the API's nginx).
            ->and($detailBytes)->toBeLessThan(250_000);
    });
});
