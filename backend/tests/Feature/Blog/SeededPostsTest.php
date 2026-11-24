<?php

use App\Models\Post;
use App\Modules\Content\Blocks\BlockRegistry;

/*
| The seeded blog content (F2 Part B): "Docker & Containers 101" is published from its content
| file, uses every block type at least once, and imports its images through the upload pipeline.
*/

it('publishes the Docker post with every block type and only the callout tones the schema allows', function () {
    $post = Post::query()->visibleToReaders()->where('slug', 'docker-containers-101')->firstOrFail();
    $sections = $post->sections()->get();
    // Changed on purpose in the F6 dogfood run: the post gained a recap and a fun fact through a content package.
    $tones = $sections->where('type', 'callout')->pluck('payload.tone')->unique();
    $allowed = app(BlockRegistry::class)->get('callout')->schema()->properties->tone->enum;

    expect($sections->pluck('type')->unique()->sort()->values()->all())
        ->toBe(collect(app(BlockRegistry::class)->all())->keys()->sort()->values()->all())
        ->and($tones->diff($allowed)->all())->toBe([])
        ->and($tones->all())->toContain('recap', 'fun-fact')
        ->and($sections->where('type', 'knowledge-check')->count())->toBeGreaterThanOrEqual(3)
        ->and($post->coverMedia?->source)->toBe('posts/media/docker-containers-101/cover.png')
        ->and($post->estimated_minutes)->toBeGreaterThan(30);

    // The dogfood run's sources reached the content file: eight sections cite official documentation.
    expect($sections->filter(fn ($s) => ! empty($s->sources))->count())->toBe(8);

    $this->getJson('/api/v1/posts/docker-containers-101')->assertOk()
        ->assertJsonPath('data.cover.width', 1600)
        ->assertJsonPath('data.sections.0.heading', 'Why containers exist');
});
