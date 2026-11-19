<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires a session for learner actions', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/courses/networking-fundamentals/progress'],
    ['POST', '/api/v1/courses/networking-fundamentals/enroll'],
    ['POST', '/api/v1/courses/networking-fundamentals/favorite'],
    ['DELETE', '/api/v1/courses/networking-fundamentals/favorite'],
]);

it('enrolls once and reports progress from the first lesson', function () {
    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/courses/networking-fundamentals/enroll')
        ->assertCreated()
        ->assertJsonPath('data.enrolled', true)
        ->assertJsonPath('data.total_lessons', 6)
        ->assertJsonPath('data.percent', 0)
        ->assertJsonPath('data.next_lesson.slug', 'intro-to-computer-networks')
        ->assertJsonPath('data.lessons.osi-model', 'not_started');

    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/courses/networking-fundamentals/enroll')
        ->assertOk();

    expect($this->user->enrollments()->count())->toBe(1);
});

it('favorites and unfavorites idempotently', function () {
    $this->actingAs($this->user, 'web')->postJson('/api/v1/courses/linux-command-line/favorite')->assertOk();
    $this->actingAs($this->user, 'web')->postJson('/api/v1/courses/linux-command-line/favorite')->assertOk();
    expect($this->user->favoriteCourses()->count())->toBe(1);

    $this->actingAs($this->user, 'web')
        ->getJson('/api/v1/courses/linux-command-line/progress')
        ->assertJsonPath('data.favorited', true);

    $this->actingAs($this->user, 'web')->deleteJson('/api/v1/courses/linux-command-line/favorite')->assertOk();
    $this->actingAs($this->user, 'web')->deleteJson('/api/v1/courses/linux-command-line/favorite')->assertOk();
    expect($this->user->favoriteCourses()->count())->toBe(0);
});

it('returns 404 for unknown courses', function () {
    $this->actingAs($this->user, 'web')->postJson('/api/v1/courses/no-such-course/enroll')->assertNotFound();
});
