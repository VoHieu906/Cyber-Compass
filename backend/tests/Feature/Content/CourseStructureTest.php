<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Content\Services\ContentImporter;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->course = Course::query()->where('slug', 'applied-cryptography')->firstOrFail();
});

it('lists every course with chapters and lessons in order', function () {
    $course = collect($this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/content/courses')->assertOk()->json('data'))
        ->firstWhere('slug', 'applied-cryptography');

    expect(collect($course['chapters'])->pluck('position')->all())->toBe([1, 2])
        ->and(collect($course['chapters'][0]['lessons'])->pluck('slug')->all())->toBe(['hashing-and-passwords', 'symmetric-asymmetric-encryption']);
});

it('adds, renames and reorders chapters, and records each change', function () {
    $added = $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/content/courses/{$this->course->id}/chapters", ['title' => 'Key management'])
        ->assertOk()->json('data.chapters');
    expect(end($added)['title'])->toBe('Key management')->and(end($added)['position'])->toBe(3);

    $new = end($added)['id'];
    $this->actingAs($this->admin, 'web')->patchJson("/api/v1/admin/content/chapters/{$new}", ['title' => 'Keys and certificates'])
        ->assertOk()->assertJsonPath('data.chapters.2.title', 'Keys and certificates');

    $ids = collect($added)->pluck('id')->all();
    $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/courses/{$this->course->id}/chapters/order", ['chapter_ids' => array_reverse($ids)])
        ->assertOk()->assertJsonPath('data.chapters.0.title', 'Keys and certificates');

    // An empty chapter never shows to learners.
    expect(collect($this->getJson('/api/v1/courses/applied-cryptography')->json('data.chapters'))->pluck('title'))->not->toContain('Keys and certificates');

    foreach (['chapter.created', 'chapter.renamed', 'chapters.reordered'] as $action) {
        $this->assertDatabaseHas('admin_audit_logs', ['action' => $action, 'actor_id' => $this->admin->id]);
    }
});

it('rejects a reorder that is not exactly the current set', function () {
    $ids = $this->course->chapters()->pluck('id')->all();

    $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/courses/{$this->course->id}/chapters/order", ['chapter_ids' => [$ids[0]]])
        ->assertJsonValidationErrors('chapter_ids');
    $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/courses/{$this->course->id}/chapters/order", ['chapter_ids' => [$ids[0], $ids[0]]])
        ->assertJsonValidationErrors('chapter_ids');
});

it('reorders lessons for learners, and a later import keeps that order', function () {
    $chapter = $this->course->chapters()->orderBy('position')->firstOrFail();
    $ids = $chapter->lessons()->pluck('id')->all();

    $this->actingAs($this->admin, 'web')->putJson("/api/v1/admin/content/chapters/{$chapter->id}/lessons/order", ['lesson_ids' => array_reverse($ids)])
        ->assertOk();

    $order = fn () => collect($this->getJson('/api/v1/courses/applied-cryptography')->json('data.chapters.0.lessons'))->pluck('slug')->all();
    expect($order())->toBe(['symmetric-asymmetric-encryption', 'hashing-and-passwords']);

    app(ContentImporter::class)->import([require database_path('seeders/content/courses/applied-cryptography.php')]);
    expect($order())->toBe(['symmetric-asymmetric-encryption', 'hashing-and-passwords'])
        ->and(Lesson::query()->whereIn('id', $ids)->pluck('updated_by')->unique()->all())->toBe([$this->admin->id]);
});
