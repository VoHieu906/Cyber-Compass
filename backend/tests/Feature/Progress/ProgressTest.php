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

/** An activity at a given UTC moment, as the listener would write it. */
function activityAt(User $user, string $utc, ActivityType $type = ActivityType::LessonCompleted, int $seconds = 0): void
{
    LearningActivity::query()->create([
        'user_id' => $user->id,
        'type' => $type,
        'subject_type' => 'lesson',
        'subject_id' => Lesson::query()->where('slug', 'linux-logs')->value('id'),
        'duration_seconds' => $seconds,
        'occurred_at' => CarbonImmutable::parse($utc, 'UTC'),
    ]);
}

/** A submitted assessment with the given domains right and everything else wrong. */
function scoredAttempt(User $user, array $rightDomains): void
{
    $attempt = app(StartAttempt::class)($user, Assessment::query()->active()->firstOrFail())['attempt'];
    foreach ($attempt->answers()->with('question.domain')->get() as $answer) {
        $right = in_array($answer->question->domain->slug, $rightDomains, true);
        $option = AssessmentOption::query()->where('assessment_question_id', $answer->assessment_question_id)->where('is_correct', $right)->first();
        app(SaveAnswer::class)($attempt, $answer->assessment_question_id, $option->id);
    }
    app(SubmitAttempt::class)($attempt);
}

function setTimezone($test, string $timezone): void
{
    $test->actingAs($test->user, 'web')->patchJson('/api/v1/me/preferences', ['timezone' => $timezone])->assertOk();
}

it('requires a session', function (string $path) {
    $this->getJson("/api/v1/progress/{$path}")->assertUnauthorized();
})->with(['summary', 'activity', 'skills', 'recent', 'next', 'courses', 'assessments']);

it('summarises a new learner as empty', function () {
    $this->actingAs($this->user, 'web')->getJson('/api/v1/progress/summary')
        ->assertOk()
        ->assertJsonPath('data.enrolled_courses', 0)
        ->assertJsonPath('data.completed_lessons', 0)
        ->assertJsonPath('data.total_minutes', 0)
        ->assertJsonPath('data.streak', ['current' => 0, 'longest' => 0, 'active_today' => false, 'last_active_on' => null])
        ->assertJsonPath('data.roadmap', null)
        ->assertJsonPath('data.timezone', 'UTC');
});

it('summarises courses, lessons, time, this week and the roadmap', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-19 09:00', 'UTC')); // a Thursday
    $this->actingAs($this->user, 'web')->postJson('/api/v1/courses/security-fundamentals/enroll')->assertSuccessful();
    $this->putJson('/api/v1/me/learning-goal', ['career' => 'soc-analyst', 'weekly_minutes' => 120])->assertOk();
    passLesson($this->user, 'cia-triad', 900);
    $this->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 300])->assertOk();
    activityAt($this->user, '2026-11-15 12:00', ActivityType::StudySession, 600); // last Sunday: before this week
    scoredAttempt($this->user, ['networking']);
    $this->postJson('/api/v1/learning-path/generate')->assertCreated();

    $data = $this->getJson('/api/v1/progress/summary')->assertOk()->json('data');

    expect($data)->toMatchArray([
        'enrolled_courses' => 1,
        'completed_courses' => 0,
        'completed_lessons' => 2,
        'total_minutes' => 30,
        'week' => ['starts_on' => '2026-11-16', 'minutes' => 20, 'goal_minutes' => 120],
    ])
        ->and($data['streak'])->toMatchArray(['current' => 1, 'active_today' => true])
        ->and($data['roadmap']['career'])->toBe('SOC Analyst')
        ->and($data['roadmap']['completion_percent'])->toBeInt();
});

it('stores the timezone as a preference and refuses unknown names', function () {
    setTimezone($this, 'Asia/Ho_Chi_Minh');
    $this->getJson('/api/v1/me')->assertJsonPath('data.profile.timezone', 'Asia/Ho_Chi_Minh');

    // Some browsers report legacy aliases; they work as local time too.
    setTimezone($this, 'Asia/Saigon');
    $this->getJson('/api/v1/progress/summary')->assertJsonPath('data.timezone', 'Asia/Saigon');

    $this->patchJson('/api/v1/me/preferences', ['timezone' => 'Mars/Olympus_Mons'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('timezone');
});

it('counts streak days in the learner\'s local date', function () {
    // 20:00 UTC on the 20th is 03:00 on the 21st in Ho Chi Minh City (UTC+7).
    activityAt($this->user, '2026-11-19 10:00');
    activityAt($this->user, '2026-11-20 20:00');
    $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00', 'UTC'));

    $this->actingAs($this->user, 'web')->getJson('/api/v1/progress/summary')
        ->assertJsonPath('data.streak', ['current' => 2, 'longest' => 2, 'active_today' => false, 'last_active_on' => '2026-11-20']);

    setTimezone($this, 'Asia/Ho_Chi_Minh');
    $this->getJson('/api/v1/progress/summary')
        ->assertJsonPath('data.streak', ['current' => 1, 'longest' => 1, 'active_today' => true, 'last_active_on' => '2026-11-21'])
        ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh');
});

it('reports weekly minutes and lessons in local weeks, oldest first', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00', 'UTC')); // a Saturday
    activityAt($this->user, '2026-08-01 10:00'); // before the 12 weeks
    activityAt($this->user, '2026-11-16 10:00', ActivityType::LessonCompleted, 1200);
    activityAt($this->user, '2026-11-17 10:00', ActivityType::StudySession, 600);
    activityAt($this->user, '2026-11-15 20:00', ActivityType::LessonCompleted, 300); // Sunday in UTC, Monday in UTC+7

    $data = $this->actingAs($this->user, 'web')->getJson('/api/v1/progress/activity')->assertOk()->json('data');
    expect($data['weeks'])->toHaveCount(12)
        ->and($data['weeks'][0]['starts_on'])->toBe('2026-08-31')
        ->and($data['weeks'][11])->toBe(['starts_on' => '2026-11-16', 'minutes' => 30, 'lessons_completed' => 1])
        ->and($data['weeks'][10])->toBe(['starts_on' => '2026-11-09', 'minutes' => 5, 'lessons_completed' => 1])
        ->and($data['lessons_completed_before'])->toBe(1);

    setTimezone($this, 'Asia/Ho_Chi_Minh');
    $weeks = $this->getJson('/api/v1/progress/activity?range=4w')->assertOk()->json('data.weeks');
    expect($weeks)->toHaveCount(4)
        ->and($weeks[3])->toBe(['starts_on' => '2026-11-16', 'minutes' => 35, 'lessons_completed' => 2]);
});

it('refuses an invalid range', function (string $range) {
    $this->actingAs($this->user, 'web')->getJson("/api/v1/progress/activity?range={$range}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('range');
})->with(['3w', '53w', '12', 'abc', '12w;drop']);

it('lists my recent activity, newest first, with what it was about', function () {
    passLesson($this->user, 'cia-triad', 900);
    scoredAttempt($this->user, []);
    $this->travel(1)->minutes();
    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/linux-logs/start')->assertOk(); // starts stay out of the feed
    $this->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 120])->assertOk();
    passLesson(User::factory()->create(), 'linux-logs');

    $data = $this->getJson('/api/v1/progress/recent')->assertOk()->json('data');

    expect($data[0])->toMatchArray(['type' => 'lesson_completed', 'minutes' => 2, 'subject' => ['kind' => 'lesson', 'slug' => 'linux-logs', 'title' => Lesson::query()->where('slug', 'linux-logs')->value('title')]])
        ->and(collect($data)->pluck('type')->all())->toContain('assessment_submitted', 'exercise_submitted')
        ->and(collect($data)->firstWhere('type', 'exercise_submitted')['subject'])->toMatchArray(['kind' => 'exercise', 'lesson' => ['slug' => 'cia-triad', 'title' => Lesson::query()->where('slug', 'cia-triad')->value('title')], 'score' => 100, 'passed' => true])
        ->and(collect($data)->firstWhere('type', 'assessment_submitted')['subject']['kind'])->toBe('assessment')
        ->and($data)->toHaveCount(4);
});

it('suggests next lessons from enrolled courses, then from the roadmap', function () {
    $this->actingAs($this->user, 'web')->getJson('/api/v1/progress/next')
        ->assertOk()->assertJsonPath('data', ['source' => null, 'lessons' => []]);

    $this->postJson('/api/v1/courses/networking-fundamentals/enroll')->assertSuccessful();
    $this->getJson('/api/v1/progress/next')
        ->assertJsonPath('data.source', 'courses')
        ->assertJsonPath('data.lessons.0.slug', 'intro-to-computer-networks')
        ->assertJsonPath('data.lessons.0.course', 'Networking Fundamentals');

    $this->putJson('/api/v1/me/learning-goal', ['career' => 'soc-analyst'])->assertOk();
    scoredAttempt($this->user, ['networking']);
    $path = $this->postJson('/api/v1/learning-path/generate')->assertCreated()->json('data');

    $next = $this->getJson('/api/v1/progress/next')->assertOk()->json('data');
    expect($next['source'])->toBe('roadmap')
        ->and($next['lessons'])->toHaveCount(3)
        ->and($next['lessons'][0]['slug'])->toBe($path['next_lesson']['slug'])
        ->and(array_keys($next['lessons'][0]))->toBe(['slug', 'title', 'topic', 'estimated_minutes'])
        ->and(collect($next['lessons'])->pluck('slug')->unique())->toHaveCount(3);
});

it('reports completion for each enrolled course', function () {
    $this->actingAs($this->user, 'web')->postJson('/api/v1/courses/networking-fundamentals/enroll')->assertSuccessful();
    passLesson($this->user, 'intro-to-computer-networks');

    $this->getJson('/api/v1/progress/courses')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'networking-fundamentals')
        ->assertJsonPath('data.0.domain.slug', 'networking')
        ->assertJsonPath('data.0.completed_lessons', 1)
        ->assertJsonPath('data.0.total_lessons', $total = Lesson::query()->visibleToLearners()->whereHas('chapter.course', fn ($q) => $q->where('slug', 'networking-fundamentals'))->count())
        ->assertJsonPath('data.0.percent', intdiv(100, $total))
        ->assertJsonPath('data.0.completed_at', null);
});

it('lists my assessment history oldest first with domain scores', function () {
    scoredAttempt($this->user, []);
    $this->travel(1)->minutes();
    scoredAttempt($this->user, ['networking']);
    scoredAttempt(User::factory()->create(), ['networking']);

    $data = $this->actingAs($this->user, 'web')->getJson('/api/v1/progress/assessments')->assertOk()->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['score'])->toBeLessThan($data[1]['score'])
        ->and(collect($data[1]['domains'])->firstWhere('slug', 'networking')['score'])->toBe(100);
});

it('reports skills by domain from the latest assessment and lesson completion', function () {
    scoredAttempt($this->user, ['networking']);
    passLesson($this->user, 'intro-to-computer-networks');

    $networking = collect($this->actingAs($this->user, 'web')->getJson('/api/v1/progress/skills')->assertOk()->json('data'))
        ->firstWhere('slug', 'networking');

    expect($networking)->toMatchArray(['score' => 100, 'band' => 'strength'])
        ->and($networking['completion']['completed'])->toBe(1);
});
