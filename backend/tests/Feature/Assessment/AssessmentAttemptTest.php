<?php

use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentOption;
use App\Models\AssessmentQuestion;
use App\Models\User;
use App\Modules\Assessment\Events\AssessmentSubmitted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/** Starts an attempt for the current user and returns its JSON. */
function startAttempt($test): array
{
    return $test->actingAs($test->user, 'web')->postJson('/api/v1/assessments/initial-knowledge/attempts')->json('data');
}

function correctOption(int $questionId): AssessmentOption
{
    return AssessmentOption::query()->where('assessment_question_id', $questionId)->where('is_correct', true)->firstOrFail();
}

function wrongOption(int $questionId): AssessmentOption
{
    return AssessmentOption::query()->where('assessment_question_id', $questionId)->where('is_correct', false)->firstOrFail();
}

it('requires a session for every assessment route', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/assessments/active'],
    ['POST', '/api/v1/assessments/initial-knowledge/attempts'],
    ['GET', '/api/v1/assessment-attempts'],
    ['GET', '/api/v1/assessment-attempts/1'],
    ['PUT', '/api/v1/assessment-attempts/1/answers/1'],
    ['POST', '/api/v1/assessment-attempts/1/submit'],
    ['GET', '/api/v1/assessment-attempts/1/result'],
]);

it('describes the active assessment with question counts per domain', function () {
    $this->actingAs($this->user, 'web')
        ->getJson('/api/v1/assessments/active')
        ->assertOk()
        ->assertJsonPath('data.slug', 'initial-knowledge')
        ->assertJsonPath('data.question_count', 40)
        ->assertJsonCount(8, 'data.domains')
        ->assertJsonPath('data.domains.0.slug', 'networking')
        ->assertJsonPath('data.domains.0.question_count', 5)
        ->assertJsonPath('data.formula.developing_from', 40)
        ->assertJsonPath('data.formula.strength_from', 75)
        ->assertJsonPath('data.open_attempt', null)
        ->assertJsonPath('data.latest_result', null);
});

it('starts an attempt with every question and never sends the answer key', function () {
    $response = $this->actingAs($this->user, 'web')->postJson('/api/v1/assessments/initial-knowledge/attempts');

    $response->assertCreated()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.total', 40)
        ->assertJsonPath('data.answered', 0)
        ->assertJsonPath('data.questions.0.domain.slug', 'networking')
        ->assertJsonPath('data.questions.0.selected_option_id', null)
        ->assertJsonStructure(['data' => ['questions' => [['id', 'prompt', 'code', 'difficulty', 'options' => [['id', 'label']]]]]]);

    $body = $response->getContent();
    expect($body)->not->toContain('is_correct')->not->toContain('explanation')->not->toContain('correct_option');
});

it('resumes the open attempt instead of starting a second one', function () {
    $first = startAttempt($this);

    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/assessments/initial-knowledge/attempts')
        ->assertOk()
        ->assertJsonPath('data.id', $first['id']);

    $this->getJson('/api/v1/assessments/active')->assertJsonPath('data.open_attempt', ['id' => $first['id'], 'answered' => 0, 'total' => 40]);
    expect(AssessmentAttempt::query()->where('user_id', $this->user->id)->count())->toBe(1);
});

it('allows only one open attempt per learner at the database level', function () {
    $attempt = AssessmentAttempt::query()->create(['user_id' => $this->user->id, 'assessment_id' => 1, 'started_at' => now()]);

    expect(fn () => AssessmentAttempt::query()->create(['user_id' => $this->user->id, 'assessment_id' => $attempt->assessment_id, 'started_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('autosaves, changes and clears an answer', function () {
    $attempt = startAttempt($this);
    $question = $attempt['questions'][0];
    $url = "/api/v1/assessment-attempts/{$attempt['id']}/answers/{$question['id']}";

    $this->putJson($url, ['option_id' => $question['options'][0]['id']])
        ->assertOk()
        ->assertJsonPath('data.selected_option_id', $question['options'][0]['id'])
        ->assertJsonPath('data.answered', 1);
    $this->putJson($url, ['option_id' => $question['options'][1]['id']])->assertJsonPath('data.answered', 1);
    $this->getJson("/api/v1/assessment-attempts/{$attempt['id']}")
        ->assertJsonPath('data.questions.0.selected_option_id', $question['options'][1]['id'])
        ->assertJsonPath('data.answered', 1);

    $this->putJson($url, ['option_id' => null])->assertJsonPath('data.answered', 0);
});

it('validates saved answers', function () {
    $attempt = startAttempt($this);
    [$first, $second] = $attempt['questions'];
    $base = "/api/v1/assessment-attempts/{$attempt['id']}/answers";

    $this->putJson("{$base}/{$first['id']}", [])->assertUnprocessable()->assertJsonValidationErrors('option_id');
    $this->putJson("{$base}/{$first['id']}", ['option_id' => 'b'])->assertUnprocessable()->assertJsonValidationErrors('option_id');
    // An option of another question.
    $this->putJson("{$base}/{$first['id']}", ['option_id' => $second['options'][0]['id']])->assertUnprocessable()->assertJsonValidationErrors('option_id');
    // A question this attempt did not draw.
    $this->putJson("{$base}/999999", ['option_id' => null])->assertNotFound();
});

it('hides other learners\' attempts behind 404', function () {
    $attempt = startAttempt($this);
    $question = $attempt['questions'][0];
    $other = User::factory()->create();
    Auth::forgetGuards();

    $this->actingAs($other, 'web');
    $this->getJson("/api/v1/assessment-attempts/{$attempt['id']}")->assertNotFound();
    $this->putJson("/api/v1/assessment-attempts/{$attempt['id']}/answers/{$question['id']}", ['option_id' => null])->assertNotFound();
    $this->postJson("/api/v1/assessment-attempts/{$attempt['id']}/submit")->assertNotFound();
    $this->getJson("/api/v1/assessment-attempts/{$attempt['id']}/result")->assertNotFound();
    $this->getJson('/api/v1/assessment-attempts')->assertJsonCount(0, 'data');
});

it('scores a submitted attempt with the published formula', function () {
    Event::fake([AssessmentSubmitted::class]);
    $attempt = startAttempt($this);
    $networking = collect($attempt['questions'])->where('domain.slug', 'networking')->values();

    // Networking: answer the two difficulty-1 questions and the difficulty-3 question correctly,
    // one difficulty-2 question wrongly and leave the other unanswered: (1 + 1 + 3) / 9 = 56%.
    foreach ($networking as $question) {
        $option = match (true) {
            $question['difficulty'] !== 2 => correctOption($question['id']),
            $question === $networking->firstWhere('difficulty', 2) => wrongOption($question['id']),
            default => null,
        };
        if ($option) {
            $this->putJson("/api/v1/assessment-attempts/{$attempt['id']}/answers/{$question['id']}", ['option_id' => $option->id])->assertOk();
        }
    }

    $result = $this->postJson("/api/v1/assessment-attempts/{$attempt['id']}/submit")->assertOk();

    $result->assertJsonPath('data.domains.0.domain.slug', 'networking')
        ->assertJsonPath('data.domains.0.score', 56)
        ->assertJsonPath('data.domains.0.band', 'developing')
        ->assertJsonPath('data.domains.0.earned', 5)
        ->assertJsonPath('data.domains.0.possible', 9)
        ->assertJsonPath('data.domains.0.answered', 4)
        ->assertJsonPath('data.domains.1.score', 0)
        ->assertJsonPath('data.domains.1.band', 'gap')
        // 5 of 8 domains × 9 weighted points = 72 possible overall.
        ->assertJsonPath('data.overall.earned', 5)
        ->assertJsonPath('data.overall.possible', 72)
        ->assertJsonPath('data.overall.score', 7)
        ->assertJsonPath('data.formula.strength_from', 75)
        ->assertJsonPath('data.strengths', [])
        ->assertJsonCount(7, 'data.gaps');

    expect(AssessmentAttempt::query()->find($attempt['id']))
        ->score_percent->toBe(7)
        ->submitted_at->not->toBeNull()
        ->and(AssessmentAnswer::query()->where('assessment_attempt_id', $attempt['id'])->whereNull('is_correct')->count())->toBe(0);

    Event::assertDispatched(AssessmentSubmitted::class, fn ($event) => $event->attempt->id === $attempt['id']);
});

it('reveals the correct answer and explanation only for questions answered wrongly or left unanswered', function () {
    $attempt = startAttempt($this);
    [$right, $wrong] = $attempt['questions'];
    $base = "/api/v1/assessment-attempts/{$attempt['id']}";
    $this->putJson("{$base}/answers/{$right['id']}", ['option_id' => correctOption($right['id'])->id]);
    $this->putJson("{$base}/answers/{$wrong['id']}", ['option_id' => wrongOption($wrong['id'])->id]);
    $this->postJson("{$base}/submit")->assertOk();

    $review = $this->getJson("{$base}/result")->assertOk()->json('data.review');

    expect($review[0])->toMatchArray(['question_id' => $right['id'], 'is_correct' => true, 'correct_option' => null, 'explanation' => null])
        ->and($review[0]['your_option']['id'])->toBe(correctOption($right['id'])->id);
    expect($review[1])->toMatchArray(['question_id' => $wrong['id'], 'is_correct' => false])
        ->and($review[1]['correct_option']['id'])->toBe(correctOption($wrong['id'])->id)
        ->and($review[1]['explanation'])->toBe(AssessmentQuestion::query()->find($wrong['id'])->explanation);
    // Unanswered: no option chosen, key and explanation shown.
    expect($review[2])->toMatchArray(['is_correct' => false, 'your_option' => null])
        ->and($review[2]['correct_option'])->not->toBeNull();
});

it('recommends the introductory course of the weakest domains first', function () {
    $attempt = startAttempt($this);
    // Everything right except the first linux and every networking answer left empty.
    foreach ($attempt['questions'] as $question) {
        if ($question['domain']['slug'] === 'networking') {
            continue;
        }
        $option = $question['domain']['slug'] === 'linux' && $question['difficulty'] === 3 ? wrongOption($question['id']) : correctOption($question['id']);
        $this->putJson("/api/v1/assessment-attempts/{$attempt['id']}/answers/{$question['id']}", ['option_id' => $option->id]);
    }

    $data = $this->postJson("/api/v1/assessment-attempts/{$attempt['id']}/submit")->json('data');

    // Linux: 6 of 9 = 67%, developing. Networking: 0, gap.
    expect($data['gaps'])->toBe(['networking'])
        ->and($data['strengths'])->toHaveCount(6)
        ->and(collect($data['next_steps'])->pluck('domain.slug')->all())->toBe(['networking', 'linux'])
        ->and($data['next_steps'][0])->toMatchArray(['kind' => 'course', 'reason' => 'gap'])
        ->and($data['next_steps'][0]['course']['slug'])->toBe('networking-fundamentals');
});

it('refuses to change or resubmit a submitted attempt', function () {
    $attempt = startAttempt($this);
    $question = $attempt['questions'][0];
    $this->postJson("/api/v1/assessment-attempts/{$attempt['id']}/submit")->assertOk();

    $this->postJson("/api/v1/assessment-attempts/{$attempt['id']}/submit")->assertStatus(409)->assertJsonPath('code', 'conflict');
    $this->putJson("/api/v1/assessment-attempts/{$attempt['id']}/answers/{$question['id']}", ['option_id' => $question['options'][0]['id']])
        ->assertStatus(409);
});

it('has no result until the attempt is submitted', function () {
    $attempt = startAttempt($this);

    $this->getJson("/api/v1/assessment-attempts/{$attempt['id']}/result")->assertStatus(409)->assertJsonPath('code', 'conflict');
});

it('lists submitted attempts, newest first, and starts a new attempt after a submission', function () {
    $first = startAttempt($this);
    $this->postJson("/api/v1/assessment-attempts/{$first['id']}/submit")->assertOk();
    $second = $this->postJson('/api/v1/assessments/initial-knowledge/attempts')->assertCreated()->json('data');
    $this->postJson("/api/v1/assessment-attempts/{$second['id']}/submit")->assertOk();
    startAttempt($this); // open, not listed

    $this->getJson('/api/v1/assessment-attempts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $second['id'])
        ->assertJsonPath('data.0.score', 0)
        ->assertJsonPath('data.0.band', 'gap')
        ->assertJsonCount(8, 'data.0.domains')
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/assessments/active')->assertJsonPath('data.latest_result.id', $second['id']);
});
