<?php

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\User;

/*
| Learners only ever see published lessons in published courses. One scope
| (Lesson::scopeVisibleToLearners / scopePublished) is applied everywhere; these tests
| pin each place a draft or archived lesson could otherwise leak.
*/

beforeEach(function () {
    $this->user = User::factory()->create();
});

dataset('hidden statuses', [LessonStatus::Draft, LessonStatus::Archived]);

it('hides the lesson itself', function (LessonStatus $status) {
    Lesson::query()->where('slug', 'tls-overview')->update(['status' => $status]);

    $this->actingAs($this->user, 'web')->getJson('/api/v1/lessons/tls-overview')->assertNotFound();
    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/tls-overview/complete', ['time_spent_seconds' => 1])->assertNotFound();
})->with('hidden statuses');

it('leaves it out of lesson counts and durations', function (LessonStatus $status) {
    Lesson::query()->where('slug', 'tls-overview')->update(['status' => $status]);

    $card = collect($this->getJson('/api/v1/courses?per_page=50')->json('data'))->firstWhere('slug', 'applied-cryptography');
    expect($card['lesson_count'])->toBe(2)->and($card['estimated_minutes'])->toBe(30);

    $this->getJson('/api/v1/courses/applied-cryptography')
        ->assertJsonPath('data.lesson_count', 2)
        ->assertJsonPath('data.estimated_minutes', 30);
})->with('hidden statuses');

it('removes it, and an emptied chapter, from the curriculum', function (LessonStatus $status) {
    Lesson::query()->where('slug', 'tls-overview')->update(['status' => $status]);

    $chapters = $this->getJson('/api/v1/courses/applied-cryptography')->json('data.chapters');

    expect($chapters)->toHaveCount(1)
        ->and(collect($chapters[0]['lessons'])->pluck('slug')->all())->toBe(['hashing-and-passwords', 'symmetric-asymmetric-encryption']);
})->with('hidden statuses');

it('excludes it from progress and navigation, so the course can still reach 100%', function (LessonStatus $status) {
    Lesson::query()->where('slug', 'tls-overview')->update(['status' => $status]);
    $this->actingAs($this->user, 'web')->postJson('/api/v1/courses/applied-cryptography/enroll');

    $this->actingAs($this->user, 'web')->getJson('/api/v1/lessons/symmetric-asymmetric-encryption')
        ->assertJsonPath('data.navigation.total', 2)
        ->assertJsonPath('data.navigation.next', null);

    foreach (['hashing-and-passwords', 'symmetric-asymmetric-encryption'] as $slug) {
        $response = $this->actingAs($this->user, 'web')->postJson("/api/v1/lessons/{$slug}/complete", ['time_spent_seconds' => 1]);
    }
    $response->assertJsonPath('data.course.total_lessons', 2)->assertJsonPath('data.course.percent', 100);
})->with('hidden statuses');

it('leaves it out of career roadmap lesson counts', function (LessonStatus $status) {
    Lesson::query()->where('slug', 'tls-overview')->update(['status' => $status]);

    $topics = collect($this->getJson('/api/v1/careers/security-engineer')->json('data.roadmap.stages'))->flatMap(fn ($s) => $s['topics']);

    expect($topics->firstWhere('slug', 'tls')['lesson_count'])->toBe(0);
})->with('hidden statuses');
