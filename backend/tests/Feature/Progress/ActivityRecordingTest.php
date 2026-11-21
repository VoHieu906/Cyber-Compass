<?php

use App\Models\Assessment;
use App\Models\Exercise;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\LearningActivity;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Modules\Assessment\Actions\StartAttempt;
use App\Modules\Assessment\Actions\SubmitAttempt;
use App\Modules\Labs\Events\LabCompleted;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function activities(User $user): array
{
    return LearningActivity::query()->where('user_id', $user->id)->orderBy('id')->get()
        ->map(fn (LearningActivity $a) => [$a->type->value, $a->subject_type, $a->duration_seconds])
        ->all();
}

it('records a lesson start once and a first completion with its time', function () {
    $lesson = Lesson::query()->where('slug', 'linux-logs')->firstOrFail();
    $this->actingAs($this->user, 'web');

    $this->postJson('/api/v1/lessons/linux-logs/start')->assertOk();
    $this->postJson('/api/v1/lessons/linux-logs/start')->assertOk();
    $this->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 600])->assertOk();

    expect(activities($this->user))->toBe([
        ['lesson_started', 'lesson', 0],
        ['lesson_completed', 'lesson', 600],
    ]);
    expect(LearningActivity::query()->where('user_id', $this->user->id)->pluck('subject_id')->unique()->all())->toBe([$lesson->id]);
});

it('records a review of a completed lesson as a study session with capped time', function () {
    $this->actingAs($this->user, 'web');
    $this->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 300])->assertOk();
    $this->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 86400])->assertOk();

    expect(activities($this->user))->toBe([
        ['lesson_completed', 'lesson', 300],
        ['study_session', 'lesson', 4 * 60 * 60],
    ]);
});

it('counts exercise time once when a passing attempt completes the lesson', function () {
    passLesson($this->user, 'cia-triad', 900);

    $rows = activities($this->user);
    expect($rows)->toContain(['exercise_submitted', 'exercise_attempt', 900])
        ->toContain(['lesson_completed', 'lesson', 0])
        ->and(array_sum(array_column($rows, 2)))->toBe(900);
});

it('records nothing when the completion is refused', function () {
    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/lessons/cia-triad/complete', ['time_spent_seconds' => 60])
        ->assertUnprocessable();

    expect(activities($this->user))->toBe([]);
});

it('records submitted assessments and completed labs', function () {
    $attempt = app(StartAttempt::class)($this->user, Assessment::query()->active()->firstOrFail())['attempt'];
    app(SubmitAttempt::class)($attempt);

    $lab = Lab::query()->offered()->firstOrFail();
    $labAttempt = LabAttempt::query()->create(['user_id' => $this->user->id, 'lab_id' => $lab->id, 'state' => [], 'state_version' => 1, 'action_count' => 3, 'checkpoints_passed' => [], 'completed_at' => now()]);
    LabCompleted::dispatch($labAttempt);

    expect(activities($this->user))->toBe([
        ['assessment_submitted', 'assessment_attempt', 0],
        ['lab_completed', 'lab', 0],
    ]);
});

it('backfills earlier progress once, splitting exercise time off the lesson', function () {
    passLesson($this->user, 'cia-triad', 900);
    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 120])->assertOk();
    // As if recorded before Phase 6: no activity rows, lesson time including the exercise's.
    LearningActivity::query()->delete();
    $ciaId = Lesson::query()->where('slug', 'cia-triad')->value('id');
    expect(LessonProgress::query()->where('lesson_id', $ciaId)->value('time_spent_seconds'))->toBe(900);

    Artisan::call('progress:backfill');
    $first = activities($this->user);
    Artisan::call('progress:backfill');

    expect(activities($this->user))->toBe($first)
        ->and($first)->toContain(['exercise_submitted', 'exercise_attempt', 900])
        ->toContain(['lesson_completed', 'lesson', 120])
        ->and(array_sum(array_column($first, 2)))->toBe(1020)
        ->and(collect($first)->where(0, 'lesson_started')->count())->toBe(2);
});
