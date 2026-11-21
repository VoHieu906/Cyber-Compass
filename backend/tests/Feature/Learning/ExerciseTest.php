<?php

use App\Enums\LessonProgressStatus;
use App\Models\Exercise;
use App\Models\ExerciseQuestion;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Modules\Learning\Events\ExerciseSubmitted;
use App\Modules\Learning\Services\QuestionTypes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/*
| Lesson exercises (ARCHITECTURE D29 to D31): no key before submission, all-or-nothing scoring,
| the key revealed only for wrong answers, a passing attempt completing the lesson, and
| sections that teach a question protected from deletion (D30).
*/

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->lesson = Lesson::query()->where('slug', 'cia-triad')->firstOrFail();
    $this->exercise = Exercise::query()->where('lesson_id', $this->lesson->id)->firstOrFail();
});

/** Answers for every question: correct ones, except the positions listed, which get a wrong response. */
function exerciseAnswers(Exercise $exercise, array $wrong = []): array
{
    $types = app(QuestionTypes::class);

    return $exercise->questions()->get()->map(function (ExerciseQuestion $q) use ($types, $wrong) {
        $response = $types->correctResponse($q->type, $q->answer);
        if (in_array($q->position, $wrong, true)) {
            $response = match ($q->type->value) {
                'true_false' => ['value' => ! $q->answer['value']],
                'fill_blank' => ['blanks' => array_fill(0, count($q->answer['blanks']), 'wrong')],
                'single_choice', 'scenario' => ['option' => collect($q->payload['options'])->pluck('id')->first(fn ($id) => $id !== $q->answer['option'])],
                'multi_choice' => ['options' => [collect($q->payload['options'])->pluck('id')->first(fn ($id) => ! in_array($id, $q->answer['options'], true))]],
                'matching' => ['matches' => array_reverse($q->answer['matches'])],
                'ordering' => ['order' => array_reverse($q->answer['order'])],
            };
        }

        return ['question_id' => $q->id, 'response' => $response];
    })->all();
}

it('requires a session', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([['GET', '/api/v1/lessons/cia-triad/exercise'], ['POST', '/api/v1/lessons/cia-triad/exercise/attempts'], ['GET', '/api/v1/exercise-attempts/1']]);

it('serves the questions with their theory sections and no answer key', function () {
    $response = $this->actingAs($this->user, 'web')->getJson('/api/v1/lessons/cia-triad/exercise')->assertOk();
    $data = $response->json('data');

    expect($data['question_count'])->toBe($this->exercise->questions()->count())
        ->and($data['pass_percent'])->toBe(70)
        ->and($data['passed'])->toBeFalse()
        ->and($data['questions'][0]['section']['id'])->toBe($this->exercise->questions()->first()->section->uuid)
        ->and($response->getContent())->not->toContain('"answer"')->not->toContain('correct');
});

it('has no exercise for a lesson without one, or for a lesson learners cannot see', function () {
    $this->actingAs($this->user, 'web')->getJson('/api/v1/lessons/hashing-and-passwords/exercise')->assertNotFound();
    $this->lesson->update(['status' => 'draft']);
    $this->getJson('/api/v1/lessons/cia-triad/exercise')->assertNotFound();
});

it('passes with every answer right, completing the lesson', function () {
    Event::fake([ExerciseSubmitted::class]);

    $data = $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/lessons/cia-triad/exercise/attempts', ['answers' => exerciseAnswers($this->exercise), 'time_spent_seconds' => 900])
        ->assertCreated()->json('data');

    expect($data)->toMatchArray(['score' => 100, 'passed' => true, 'lesson_status' => 'completed'])
        ->and(collect($data['questions'])->pluck('correct_answer')->unique()->all())->toBe([null])
        ->and(collect($data['questions'])->every(fn ($q) => $q['explanation'] !== '' && $q['section'] !== null))->toBeTrue();
    $progress = LessonProgress::query()->where('user_id', $this->user->id)->where('lesson_id', $this->lesson->id)->sole();
    expect($progress->status)->toBe(LessonProgressStatus::Completed)->and($progress->time_spent_seconds)->toBe(900);
    Event::assertDispatched(ExerciseSubmitted::class);
});

it('fails below the pass mark, revealing the key only for wrong answers, and leaves the lesson started', function () {
    $total = $this->exercise->questions()->count();
    $data = $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/lessons/cia-triad/exercise/attempts', ['answers' => exerciseAnswers($this->exercise, [1, 2])])
        ->assertCreated()->json('data');

    expect($data)->toMatchArray(['score' => (int) round(($total - 2) / $total * 100), 'passed' => false, 'lesson_status' => 'started', 'correct' => $total - 2]);
    foreach ($data['questions'] as $q) {
        expect($q['correct_answer'] === null)->toBe($q['is_correct']);
    }
    expect(collect($data['questions'])->firstWhere('position', 1)['correct_answer'])
        ->toBe(ExerciseQuestion::query()->where('exercise_id', $this->exercise->id)->where('position', 1)->first()->answer);
});

it('counts a question left out as wrong', function () {
    $answers = array_slice(exerciseAnswers($this->exercise), 1);
    $data = $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/cia-triad/exercise/attempts', ['answers' => $answers])->assertCreated()->json('data');

    expect(collect($data['questions'])->firstWhere('position', 1))->toMatchArray(['is_correct' => false, 'response' => null]);
});

it('rejects malformed answers before writing anything', function (Closure $change, string $field) {
    $answers = exerciseAnswers($this->exercise);
    $answers = $change($answers);

    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/cia-triad/exercise/attempts', ['answers' => $answers])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($this->user->exerciseAttempts()->count())->toBe(0);
})->with([
    'a response of the wrong shape' => [fn ($a) => [['question_id' => $a[0]['question_id'], 'response' => ['nonsense' => 1]], ...array_slice($a, 1)], 'answers.0.response'],
    'a question from another exercise' => [fn ($a) => [['question_id' => ExerciseQuestion::query()->whereNot('exercise_id', test()->exercise->id)->value('id'), 'response' => null], ...$a], 'answers.0.question_id'],
    'the same question twice' => [fn ($a) => [...$a, $a[0]], 'answers.'.'5.question_id'],
    'no answers list' => [fn ($a) => 'all of them', 'answers'],
]);

it('blocks completing a lesson by hand until its exercise is passed', function () {
    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/cia-triad/complete', ['time_spent_seconds' => 60])
        ->assertUnprocessable()->assertJsonValidationErrors('exercise');

    passLesson($this->user, $this->lesson);
    $this->postJson('/api/v1/lessons/cia-triad/complete', ['time_spent_seconds' => 60])->assertOk()->assertJsonPath('data.status', 'completed');
});

it('keeps completions recorded before the lesson had an exercise', function () {
    LessonProgress::query()->create(['user_id' => $this->user->id, 'lesson_id' => $this->lesson->id, 'status' => 'completed', 'started_at' => now(), 'completed_at' => now(), 'time_spent_seconds' => 0]);

    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/cia-triad/complete', ['time_spent_seconds' => 60])->assertOk();
});

it('shows my attempt to me and to nobody else', function () {
    $id = $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/cia-triad/exercise/attempts', ['answers' => exerciseAnswers($this->exercise)])->json('data.id');
    $this->getJson("/api/v1/exercise-attempts/{$id}")->assertOk()->assertJsonPath('data.score', 100);

    Auth::forgetGuards();
    $this->actingAs(User::factory()->create(), 'web')->getJson("/api/v1/exercise-attempts/{$id}")->assertNotFound();
});

it('tells the reader and the roadmap about the exercise', function () {
    $lesson = $this->actingAs($this->user, 'web')->getJson('/api/v1/lessons/cia-triad')->json('data.exercise');
    expect($lesson)->toBe(['question_count' => $this->exercise->questions()->count(), 'pass_percent' => 70, 'passed' => false])
        ->and($this->getJson('/api/v1/lessons/hashing-and-passwords')->json('data.exercise'))->toBeNull();

    passLesson($this->user, $this->lesson);
    expect($this->getJson('/api/v1/lessons/cia-triad')->json('data.exercise.passed'))->toBeTrue();
    $row = collect($this->getJson('/api/v1/knowledge-map')->json('data.topics'))->flatMap(fn ($t) => $t['lessons'])->firstWhere('slug', 'cia-triad');
    expect($row['exercise'])->toBe(['passed' => true]);
});

describe('sections that teach a question (D30)', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->referenced = $this->exercise->questions()->first()->section;
    });

    function publishWithout(string $uuid): TestResponse
    {
        $lesson = test()->lesson;
        $data = test()->actingAs(test()->admin, 'web')->getJson("/api/v1/admin/content/lessons/{$lesson->id}")->assertOk()->json('data');
        $sections = array_values(array_filter($data['working_copy']['sections'], fn ($s) => $s['id'] !== $uuid));
        $saved = test()->putJson("/api/v1/admin/content/lessons/{$lesson->id}/draft", [...$data['working_copy'], 'sections' => $sections, 'lock_version' => $data['lock_version']])->assertOk()->json('data');

        return test()->postJson("/api/v1/admin/content/lessons/{$lesson->id}/publish", ['lock_version' => $saved['lock_version']]);
    }

    it('lists them for the editor', function () {
        $refs = $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/content/lessons/{$this->lesson->id}")->json('data.exercise_references');

        expect($refs[$this->referenced->uuid])->toContain('cia-triad/'.$this->exercise->questions()->first()->key);
    });

    it('refuses to publish a draft that removes one', function () {
        publishWithout($this->referenced->uuid)->assertConflict()->assertJsonPath('message', fn ($m) => str_contains($m, $this->referenced->heading));

        expect($this->referenced->fresh())->not->toBeNull();
    });

    it('lets a section go once no active question uses it, clearing archived references', function () {
        $question = $this->exercise->allQuestions()->where('lesson_section_id', $this->referenced->id)->get();
        ExerciseQuestion::query()->whereIn('id', $question->pluck('id'))->update(['is_archived' => true]);

        publishWithout($this->referenced->uuid)->assertOk();

        expect($this->referenced->fresh())->toBeNull()
            ->and(ExerciseQuestion::query()->whereIn('id', $question->pluck('id'))->pluck('lesson_section_id')->unique()->all())->toBe([null]);
    });
});
