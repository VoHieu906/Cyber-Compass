<?php

use App\Enums\LessonStatus;
use App\Models\LessonMedia;
use App\Models\Post;
use App\Models\PostSection;
use App\Models\User;
use App\Modules\Blog\Actions\PostLifecycle;
use App\Modules\Content\Services\ContentExporter;
use App\Modules\Content\Services\ContentFileMedia;
use App\Modules\Content\Services\ContentImporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
| Blog posts in content files (F2, D55) and images named by file (D56): the lesson import rules
| applied to posts, and export writing back a file that imports without changing anything.
*/

beforeEach(function () {
    Storage::fake(config('cybercompass.uploads_disk'));
    // Only this test's images and posts: the seeded blog (and its images) would otherwise be archived or counted.
    Post::query()->delete();
    LessonMedia::query()->delete();
    // A writable stand-in for database/seeders/content (CI's checkout is not writable by the app user).
    config(['cybercompass.content_root' => storage_path('framework/testing/content-'.uniqid())]);
    $this->dir = 'posts/media/test';
    File::makeDirectory(ContentFileMedia::root().'/'.$this->dir, 0777, true);
    $image = imagecreatetruecolor(40, 20);
    imagefilledrectangle($image, 0, 0, 39, 19, imagecolorallocate($image, 30, 120, 200));
    imagepng($image, ContentFileMedia::root()."/{$this->dir}/layers.png");
});

afterEach(function () {
    File::deleteDirectory(ContentFileMedia::root());
});

function postFile(string $dir, array $changes = []): array
{
    return [
        'slug' => 'import-test-post',
        'title' => 'Import test post',
        'summary' => 'A post from a content file.',
        'tags' => ['docker'],
        'published_at' => '2026-11-22',
        'cover' => ['file' => "{$dir}/layers.png", 'alt' => 'Image layers stacked'],
        'sections' => [
            ['prose', 'Why containers', 'Containers share **one** kernel.'],
            ['callout', null, 'Back up your volumes.', 'warning'],
            ['media', 'Layers', ['media' => ['file' => "{$dir}/layers.png", 'alt' => 'Image layers stacked'], 'caption' => 'Each instruction adds a layer.']],
            ['code', 'Try it', 'docker run --rm alpine echo hi', 'bash'],
        ],
        ...$changes,
    ];
}

it('imports a post with derived section UUIDs and re-encoded images from files', function () {
    $report = app(ContentImporter::class)->importPosts([postFile($this->dir)]);
    $post = Post::query()->where('slug', 'import-test-post')->firstOrFail();
    $media = LessonMedia::query()->where('source', "{$this->dir}/layers.png")->sole();

    expect($report)->toMatchArray(['posts' => 1, 'posts_written' => 1])
        ->and($post->status)->toBe(LessonStatus::Published)
        ->and($post->published_at->toDateString())->toBe('2026-11-22')
        ->and($post->cover)->toBe($media->uuid)
        ->and($post->estimated_minutes)->toBeGreaterThanOrEqual(1)
        ->and($post->sections()->pluck('uuid')->first())->toBe(ContentImporter::postSectionUuid('import-test-post', 'why-containers'))
        ->and($post->sections()->where('type', 'media')->value('payload')['media'])->toBe($media->uuid)
        ->and($media->alt)->toBe('Image layers stacked')
        ->and($media->path)->toBe("lesson-media/{$media->uuid}.webp")
        ->and($media->uploaded_by)->toBeNull();
    Storage::disk(config('cybercompass.uploads_disk'))->assertExists($media->path);
    expect(substr(Storage::disk(config('cybercompass.uploads_disk'))->get($media->path), 8, 4))->toBe('WEBP');

    // Importing again reuses the image and changes nothing.
    $rows = fn () => PostSection::query()->where('post_id', $post->id)->orderBy('position')->get(['id', 'uuid', 'position', 'type', 'heading', 'payload'])->toArray();
    $before = [$post->fresh()->updated_at, $rows()];
    $this->travel(1)->minute();
    app(ContentImporter::class)->importPosts([postFile($this->dir)]);
    expect(LessonMedia::query()->count())->toBe(1)
        ->and([$post->fresh()->updated_at, $rows()])->toEqual($before);
});

it('validates everything before writing anything', function () {
    $bad = postFile($this->dir, [
        'tags' => ['Not OK'],
        'cover' => ['file' => "{$this->dir}/missing.png", 'alt' => 'x'],
        'sections' => [['callout', null, 'Hi', 'loud'], ['media', null, ['media' => ['file' => '../../../.env', 'alt' => 'x']]]],
    ]);

    try {
        app(ContentImporter::class)->importPosts([postFile($this->dir, ['slug' => 'good-post']), $bad]);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toContain(
            'posts.import-test-post.tags',
            'posts.import-test-post.cover',
            'posts.import-test-post.sections.0.payload.tone',
            'posts.import-test-post.sections.1.payload.media',
        );
    }

    expect(Post::query()->whereIn('slug', ['good-post', 'import-test-post'])->exists())->toBeFalse()
        ->and(LessonMedia::query()->count())->toBe(0);
});

it('refuses SVG images, missing alt text and more than 150 sections', function () {
    file_put_contents(ContentFileMedia::root()."/{$this->dir}/logo.svg", '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $many = array_fill(0, Post::MAX_SECTIONS + 1, ['prose', null, 'x']);

    expect(fn () => app(ContentImporter::class)->importPosts([postFile($this->dir, ['cover' => ['file' => "{$this->dir}/logo.svg", 'alt' => 'Logo']])]))
        ->toThrow(ValidationException::class, 'SVG');
    expect(fn () => app(ContentImporter::class)->importPosts([postFile($this->dir, ['cover' => ['file' => "{$this->dir}/layers.png", 'alt' => ' ']])]))
        ->toThrow(ValidationException::class, 'alt text');
    expect(fn () => app(ContentImporter::class)->importPosts([postFile($this->dir, ['cover' => ['file' => "{$this->dir}/layers.png", 'alt' => str_repeat('a', 251)]])]))
        ->toThrow(ValidationException::class, 'at most 250');
    expect(fn () => app(ContentImporter::class)->importPosts([postFile($this->dir, ['sections' => $many])]))
        ->toThrow(ValidationException::class, 'At most 150');
});

it('skips posts edited in the admin unless forced, and archives seed posts that left the files', function () {
    $importer = app(ContentImporter::class);
    $importer->importPosts([postFile($this->dir), postFile($this->dir, ['slug' => 'leaving-post'])]);
    $admin = User::factory()->admin()->create();
    $post = Post::query()->where('slug', 'import-test-post')->firstOrFail();
    $post->update(['title' => 'Edited in the admin', 'updated_by' => $admin->id]);

    $report = $importer->importPosts([postFile($this->dir)]);

    expect($report['skipped'])->toBe(['import-test-post'])
        ->and($report['archived'])->toContain('leaving-post')
        ->and($post->fresh()->title)->toBe('Edited in the admin')
        ->and(Post::query()->where('slug', 'leaving-post')->value('status'))->toBe(LessonStatus::Archived);

    $importer->importPosts([postFile($this->dir)], force: true);
    expect($post->fresh()->title)->toBe('Import test post');

    // An admin-made post is never archived by an import.
    $mine = app(PostLifecycle::class)->create($admin, 'Mine', 'admin-made');
    $importer->importPosts([postFile($this->dir)]);
    expect($mine->fresh()->status)->toBe(LessonStatus::Draft);
});

it('exports a post to a file that imports without changing anything', function () {
    app(ContentImporter::class)->importPosts([postFile($this->dir)]);
    $post = Post::query()->where('slug', 'import-test-post')->firstOrFail();
    // A section made in the admin keeps its random UUID through export (pinned 'uuid').
    PostSection::query()->create(['uuid' => '11111111-2222-4333-8444-555555555555', 'post_id' => $post->id, 'position' => 5, 'type' => 'prose', 'heading' => 'Added later', 'payload' => ['text' => 'New.']]);

    $php = app(ContentExporter::class)->exportPost($post->fresh())['php'];
    $path = storage_path('app/export-test-'.uniqid().'.php');
    file_put_contents($path, $php);
    $exported = require $path;
    unlink($path);

    expect($exported['cover'])->toBe(['file' => "{$this->dir}/layers.png", 'alt' => 'Image layers stacked'])
        ->and($exported['sections'][2][2]['media'])->toBe(['file' => "{$this->dir}/layers.png", 'alt' => 'Image layers stacked'])
        ->and($exported['sections'][4]['uuid'])->toBe('11111111-2222-4333-8444-555555555555')
        ->and($exported['sections'][0])->not->toHaveKey('uuid');

    $before = PostSection::query()->where('post_id', $post->id)->orderBy('position')->get(['uuid', 'position', 'type', 'heading', 'payload'])->toArray();
    app(ContentImporter::class)->importPosts([$exported]);
    $after = PostSection::query()->where('post_id', $post->id)->orderBy('position')->get(['uuid', 'position', 'type', 'heading', 'payload'])->toArray();

    expect($after)->toEqual($before)
        ->and(LessonMedia::query()->count())->toBe(1)
        ->and($post->fresh()->only(['title', 'summary', 'tags', 'cover']))->toEqual($post->only(['title', 'summary', 'tags', 'cover']));
    $this->artisan('content:export', ['course' => 'import-test-post', '--post' => true, '--stdout' => true])
        ->expectsOutputToContain("'slug' => 'import-test-post'")->assertSuccessful();
});
