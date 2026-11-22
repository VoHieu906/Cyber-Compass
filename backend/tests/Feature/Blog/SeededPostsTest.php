<?php

use App\Models\Post;
use App\Modules\Content\Blocks\BlockRegistry;

/*
| The seeded blog content (F2 Part B): "Docker & Containers 101" is published from its content
| file, uses every block type at least once, and imports its images through the upload pipeline.
*/

it('publishes the Docker post with every block type and all three callout tones', function () {
    $post = Post::query()->visibleToReaders()->where('slug', 'docker-containers-101')->firstOrFail();
    $sections = $post->sections()->get();

    expect($sections->pluck('type')->unique()->sort()->values()->all())
        ->toBe(collect(app(BlockRegistry::class)->all())->keys()->sort()->values()->all())
        ->and($sections->where('type', 'callout')->pluck('payload.tone')->unique()->sort()->values()->all())->toBe(['info', 'tip', 'warning'])
        ->and($sections->where('type', 'knowledge-check')->count())->toBeGreaterThanOrEqual(3)
        ->and($post->coverMedia?->source)->toBe('posts/media/docker-containers-101/cover.png')
        ->and($post->estimated_minutes)->toBeGreaterThan(30);

    $this->getJson('/api/v1/posts/docker-containers-101')->assertOk()
        ->assertJsonPath('data.cover.width', 1600)
        ->assertJsonPath('data.sections.0.heading', 'Why containers exist');
});
