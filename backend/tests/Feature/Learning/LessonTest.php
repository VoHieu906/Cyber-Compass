<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exercise;
use App\Models\LessonProgress;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires a session to read a lesson', function () {
    $this->getJson('/api/v1/lessons/osi-model')->assertUnauthorized();
});

it('returns a lesson with typed sections, context and navigation', function () {
    $this->actingAs($this->user, 'web')
        ->getJson('/api/v1/lessons/osi-model')
        ->assertOk()
        ->assertJsonPath('data.course.slug', 'networking-fundamentals')
        ->assertJsonPath('data.chapter.position', 1)
        ->assertJsonPath('data.navigation.position', 2)
        ->assertJsonPath('data.navigation.total', 6)
        ->assertJsonPath('data.navigation.previous.slug', 'intro-to-computer-networks')
        ->assertJsonPath('data.navigation.next.slug', 'tcp-ip-model')
        ->assertJsonPath('data.sections.1.type', 'layer-stack')
        ->assertJsonPath('data.sections.1.payload.layers.0.name', 'Application')
        ->assertJsonPath('data.progress.status', 'not_started');
});

it('crosses chapter boundaries in navigation', function () {
    $this->actingAs($this->user, 'web')
        ->getJson('/api/v1/lessons/tcp-ip-model')
        ->assertJsonPath('data.navigation.next.slug', 'ip-addressing');

    $this->actingAs($this->user, 'web')
        ->getJson('/api/v1/lessons/dns-and-dhcp')
        ->assertJsonPath('data.navigation.next', null);
});

it('hides lessons of unpublished courses', function () {
    Course::query()->where('slug', 'networking-fundamentals')->update(['is_published' => false]);

    $this->actingAs($this->user, 'web')->getJson('/api/v1/lessons/osi-model')->assertNotFound();
});

it('starts a lesson once', function () {
    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/osi-model/start')->assertJsonPath('data.status', 'started');
    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/osi-model/start')->assertJsonPath('data.status', 'started');

    expect(LessonProgress::query()->where('user_id', $this->user->id)->count())->toBe(1);
});

it('completes a lesson, accumulates capped study time and keeps the first completion date', function () {
    // The read-to-the-end rule on its own: this lesson's exercise is removed here (D31 is covered in ExerciseTest).
    Exercise::query()->whereHas('lesson', fn ($q) => $q->where('slug', 'osi-model'))->delete();

    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/lessons/osi-model/complete', ['time_spent_seconds' => 300])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.course.completed_lessons', 1)
        ->assertJsonPath('data.course.next_lesson.slug', 'intro-to-computer-networks');

    $first = LessonProgress::query()->where('user_id', $this->user->id)->firstOrFail();
    $this->travel(1)->day();

    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/lessons/osi-model/complete', ['time_spent_seconds' => 86400])
        ->assertOk();

    $progress = $first->fresh();
    expect($progress->time_spent_seconds)->toBe(300 + 4 * 60 * 60)
        ->and($progress->completed_at->equalTo($first->completed_at))->toBeTrue();
});

it('validates the reported study time', function () {
    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/lessons/osi-model/complete', ['time_spent_seconds' => -5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('time_spent_seconds');
});

it('marks the enrollment complete when the last lesson is finished', function () {
    $this->actingAs($this->user, 'web')->postJson('/api/v1/courses/applied-cryptography/enroll');

    foreach (['hashing-and-passwords', 'symmetric-asymmetric-encryption'] as $slug) {
        $this->actingAs($this->user, 'web')->postJson("/api/v1/lessons/{$slug}/complete", ['time_spent_seconds' => 60]);
    }
    expect(Enrollment::query()->where('user_id', $this->user->id)->value('completed_at'))->toBeNull();

    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/lessons/tls-overview/complete', ['time_spent_seconds' => 60])
        ->assertJsonPath('data.course.percent', 100)
        ->assertJsonPath('data.course.next_lesson', null);

    expect(Enrollment::query()->where('user_id', $this->user->id)->value('completed_at'))->not->toBeNull();
});

it('keeps each learner\'s progress separate', function () {
    $other = User::factory()->create();
    $this->actingAs($other, 'web')->postJson('/api/v1/lessons/osi-model/complete', ['time_spent_seconds' => 60]);
    // Guards cache their user for the life of the app; a real request always starts fresh.
    Auth::forgetGuards();

    $this->actingAs($this->user, 'web')
        ->getJson('/api/v1/lessons/osi-model')
        ->assertJsonPath('data.progress.status', 'not_started');
});
