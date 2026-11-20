<?php

use App\Models\Assessment;
use App\Models\AssessmentOption;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Topic;
use App\Models\User;
use App\Modules\Assessment\Actions\SaveAnswer;
use App\Modules\Assessment\Actions\StartAttempt;
use App\Modules\Assessment\Actions\SubmitAttempt;
use App\Modules\Learning\Actions\RecordLessonProgress;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function setGoal($test, array $goal = ['career' => 'soc-analyst']): void
{
    $test->actingAs($test->user, 'web')->putJson('/api/v1/me/learning-goal', $goal)->assertOk();
}

/** Submits an attempt with every question of the given domains right and the rest wrong. */
function submitAssessment(User $user, array $rightDomains): void
{
    $attempt = app(StartAttempt::class)($user, Assessment::query()->active()->firstOrFail())['attempt'];
    foreach ($attempt->answers()->with('question.domain')->get() as $answer) {
        $right = in_array($answer->question->domain->slug, $rightDomains, true);
        $option = AssessmentOption::query()->where('assessment_question_id', $answer->assessment_question_id)->where('is_correct', $right)->first();
        app(SaveAnswer::class)($attempt, $answer->assessment_question_id, $option->id);
    }
    app(SubmitAttempt::class)($attempt);
}

function completeLesson(User $user, string $slug): void
{
    app(RecordLessonProgress::class)->complete($user, Lesson::query()->where('slug', $slug)->firstOrFail(), 600);
}

function topicLessons(string $topic): array
{
    return Topic::query()->where('slug', $topic)->firstOrFail()->lessons()->pluck('lessons.slug')->all();
}

it('requires a session', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([['GET', '/api/v1/learning-path'], ['POST', '/api/v1/learning-path/generate'], ['GET', '/api/v1/learning-paths']]);

it('has no roadmap before the first one is built', function () {
    $this->actingAs($this->user, 'web')->getJson('/api/v1/learning-path')->assertOk()->assertExactJson(['data' => null]);
});

it('needs a career goal to build a roadmap', function () {
    $this->actingAs($this->user, 'web')->postJson('/api/v1/learning-path/generate')->assertUnprocessable()->assertJsonValidationErrors('career');
});

it('builds a roadmap from the goal alone, every topic required', function () {
    setGoal($this);

    $data = $this->postJson('/api/v1/learning-path/generate')->assertCreated()->json('data');

    expect($data)->toMatchArray(['version' => 1, 'status' => 'active', 'career' => ['slug' => 'soc-analyst', 'title' => 'SOC Analyst'], 'assessment' => null, 'completion_percent' => 0, 'outdated' => []])
        ->and($data['nodes'])->toHaveCount(9)
        ->and(collect($data['nodes'])->pluck('recommendation')->unique()->all())->toBe(['required'])
        ->and($data['nodes'][0]['reason'])->toBe(['key' => 'career', 'params' => ['career' => 'SOC Analyst']])
        ->and($data['edges'])->toContain(['from' => 'linux-shell', 'to' => 'linux-investigation'])
        ->and($data['next_lesson']['slug'])->toBe(topicLessons('security-principles')[0]);

    $this->getJson('/api/v1/learning-path')->assertJsonPath('data.id', $data['id']);
});

it('uses the latest submitted assessment', function () {
    setGoal($this);
    submitAssessment($this->user, ['networking']);

    $data = $this->postJson('/api/v1/learning-path/generate')->assertCreated()->json('data');
    $nodes = collect($data['nodes'])->keyBy('topic.slug');

    expect($data['assessment']['score'])->toBe(13) // 9 of 72 points
        ->and($nodes['networking-basics']['recommendation'])->toBe('review')
        ->and($nodes['networking-basics']['reason'])->toEqual(['key' => 'strength', 'params' => ['domain' => 'Networking', 'score' => 100]])
        ->and($nodes['linux-shell']['reason']['key'])->toBe('gap');
});

it('computes topic status from lesson progress and prerequisites', function () {
    setGoal($this);
    $this->postJson('/api/v1/learning-path/generate')->assertCreated();
    $status = fn () => collect($this->getJson('/api/v1/learning-path')->json('data.nodes'))->pluck('status', 'topic.slug');

    expect($status())->toMatchArray([
        'linux-shell' => 'available',          // no prerequisites
        'linux-investigation' => 'locked',     // needs linux-shell
    ]);

    [$first, $second] = topicLessons('linux-shell');
    completeLesson($this->user, $first);
    expect($status()['linux-shell'])->toBe('in_progress');
    $node = collect($this->getJson('/api/v1/learning-path')->json('data.nodes'))->firstWhere('topic.slug', 'linux-shell');
    expect($node['percent'])->toBe(50)
        ->and($node['lessons'][0])->toMatchArray(['slug' => $first, 'status' => 'completed', 'is_required' => true]);

    completeLesson($this->user, $second);
    expect($status())->toMatchArray(['linux-shell' => 'completed', 'linux-investigation' => 'available']);
});

it('opens topics whose prerequisites are marked for review', function () {
    setGoal($this);
    submitAssessment($this->user, ['networking']);
    $this->postJson('/api/v1/learning-path/generate');

    $nodes = collect($this->getJson('/api/v1/learning-path')->json('data.nodes'))->keyBy('topic.slug');

    // addressing-and-services needs networking-basics, which is only a review topic.
    expect($nodes['addressing-and-services']['status'])->toBe('available');
});

it('reports completion and the remaining time over required topics only', function () {
    setGoal($this, ['career' => 'soc-analyst', 'weekly_minutes' => 60]);
    $data = $this->postJson('/api/v1/learning-path/generate')->json('data');
    $total = collect($data['nodes'])->flatMap(fn ($n) => $n['lessons'])->sum('estimated_minutes');

    expect($data['estimate'])->toBe(['remaining_minutes' => $total, 'weekly_minutes' => 60, 'weeks' => (int) ceil($total / 60)]);

    completeLesson($this->user, topicLessons('security-principles')[0]);
    $data = $this->getJson('/api/v1/learning-path')->json('data');
    $lessons = collect($data['nodes'])->flatMap(fn ($n) => $n['lessons']);
    expect($data['completion_percent'])->toBe((int) round(1 / $lessons->count() * 100));
});

it('creates a new version on request, archives the old one and keeps progress', function () {
    setGoal($this);
    $v1 = $this->postJson('/api/v1/learning-path/generate')->json('data');
    completeLesson($this->user, topicLessons('linux-shell')[0]);

    $v2 = $this->postJson('/api/v1/learning-path/generate')->assertCreated()->json('data');

    expect($v2['version'])->toBe(2)
        ->and(LearningPath::query()->find($v1['id'])->status)->toBe('archived')
        ->and(collect($v2['nodes'])->firstWhere('topic.slug', 'linux-shell')['status'])->toBe('in_progress');
    $this->getJson('/api/v1/learning-paths')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.version', 2)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.1.status', 'archived')
        ->assertJsonPath('data.0.topic_count', 9);
});

it('allows only one active roadmap per learner at the database level', function () {
    setGoal($this);
    $this->postJson('/api/v1/learning-path/generate');
    $path = LearningPath::query()->where('user_id', $this->user->id)->firstOrFail();

    expect(fn () => LearningPath::query()->create(['user_id' => $this->user->id, 'career_id' => $path->career_id, 'version' => 9, 'status' => 'active', 'generated_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('says when the roadmap is out of date, and never rebuilds it by itself', function () {
    setGoal($this);
    $id = $this->postJson('/api/v1/learning-path/generate')->json('data.id');

    submitAssessment($this->user, []);
    setGoal($this, ['career' => 'penetration-tester']);

    $this->getJson('/api/v1/learning-path')
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.career.slug', 'soc-analyst')
        ->assertJsonPath('data.outdated', ['career_changed', 'new_assessment']);

    $this->postJson('/api/v1/learning-path/generate')->assertJsonPath('data.career.slug', 'penetration-tester')->assertJsonPath('data.outdated', []);
});

it('keeps each learner\'s roadmap private', function () {
    setGoal($this);
    $this->postJson('/api/v1/learning-path/generate');
    Auth::forgetGuards();
    $other = User::factory()->create();

    $this->actingAs($other, 'web')->getJson('/api/v1/learning-path')->assertExactJson(['data' => null]);
    $this->getJson('/api/v1/learning-paths')->assertJsonCount(0, 'data');
});

it('orders next steps by the learner\'s interests within a band', function () {
    setGoal($this, ['domains' => ['cryptography']]);
    submitAssessment($this->user, []);
    $attemptId = $this->user->assessmentAttempts()->value('id');

    $steps = $this->getJson("/api/v1/assessment-attempts/{$attemptId}/result")->json('data.next_steps');

    // Every domain is a 0% gap; the learner's interest comes first.
    expect($steps[0]['domain']['slug'])->toBe('cryptography');
});
