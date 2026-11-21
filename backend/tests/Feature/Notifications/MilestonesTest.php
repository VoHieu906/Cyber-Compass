<?php

use App\Enums\ActivityType;
use App\Models\Assessment;
use App\Models\AssessmentOption;
use App\Models\LearningActivity;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Assessment\Actions\SaveAnswer;
use App\Modules\Assessment\Actions\StartAttempt;
use App\Modules\Assessment\Actions\SubmitAttempt;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function milestones(User $user): array
{
    return $user->notifications()->where('type', 'milestone')->oldest()->get()->map(fn ($n) => $n->data['params'])->all();
}

function studiedOn(User $user, string $utc): void
{
    LearningActivity::query()->create([
        'user_id' => $user->id,
        'type' => ActivityType::StudySession,
        'subject_type' => 'lesson',
        'subject_id' => Lesson::query()->where('slug', 'linux-logs')->value('id'),
        'duration_seconds' => 600,
        'occurred_at' => CarbonImmutable::parse($utc, 'UTC'),
    ]);
}

it('marks a finished course once', function () {
    $this->actingAs($this->user, 'web')->postJson('/api/v1/courses/security-fundamentals/enroll')->assertSuccessful();
    $slugs = collect($this->getJson('/api/v1/courses/security-fundamentals')->json('data.chapters'))->flatMap(fn ($c) => array_column($c['lessons'], 'slug'));

    foreach ($slugs as $i => $slug) {
        passLesson($this->user, $slug);
        expect(milestones($this->user))->toHaveCount($i === $slugs->count() - 1 ? 1 : 0);
    }
    passLesson($this->user, $slugs->first());

    expect(milestones($this->user))->toEqual([['kind' => 'course', 'title' => 'Security Fundamentals', 'slug' => 'security-fundamentals']])
        ->and($this->user->notifications()->where('type', 'milestone')->value('data')['url'])->toBe('/courses/security-fundamentals');
});

it('marks each roadmap quarter once per career, even on a new roadmap version', function () {
    $this->actingAs($this->user, 'web')->putJson('/api/v1/me/learning-goal', ['career' => 'soc-analyst'])->assertOk();
    $attempt = app(StartAttempt::class)($this->user, Assessment::query()->active()->firstOrFail())['attempt'];
    foreach ($attempt->answers()->get() as $answer) {
        app(SaveAnswer::class)($attempt, $answer->assessment_question_id, AssessmentOption::query()->where('assessment_question_id', $answer->assessment_question_id)->where('is_correct', false)->value('id'));
    }
    app(SubmitAttempt::class)($attempt);
    $this->postJson('/api/v1/learning-path/generate')->assertCreated();

    for ($i = 0; $i < 40 && $this->getJson('/api/v1/progress/summary')->json('data.roadmap.completion_percent') < 25; $i++) {
        passLesson($this->user, $this->getJson('/api/v1/progress/next')->json('data.lessons.0.slug'));
    }
    expect(milestones($this->user))->toEqual([['kind' => 'roadmap', 'percent' => 25, 'title' => 'SOC Analyst']]);

    // A new version for the same career does not announce 25% again.
    $this->postJson('/api/v1/learning-path/generate')->assertCreated();
    passLesson($this->user, $this->getJson('/api/v1/progress/next')->json('data.lessons.0.slug'));
    expect(collect(milestones($this->user))->where('kind', 'roadmap')->pluck('percent')->duplicates())->toBeEmpty();
});

it('marks a 7-day streak on its seventh day, once', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00', 'UTC'));
    foreach (range(6, 1) as $daysAgo) {
        studiedOn($this->user, now()->subDays($daysAgo)->toDateTimeString());
    }
    expect(milestones($this->user))->toBe([]);

    passLesson($this->user, 'linux-logs');
    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 60])->assertOk();

    expect(milestones($this->user))->toEqual([['kind' => 'streak', 'days' => 7]]);
});

it('does not repeat a milestone after its notification is deleted', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00', 'UTC'));
    foreach (range(6, 1) as $daysAgo) {
        studiedOn($this->user, now()->subDays($daysAgo)->toDateTimeString());
    }
    passLesson($this->user, 'linux-logs');
    $this->user->notifications()->delete();

    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 60])->assertOk();

    expect(milestones($this->user))->toBe([]);
});
