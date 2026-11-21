<?php

use App\Enums\ExerciseQuestionType as Type;
use App\Modules\Learning\Services\QuestionTypes;

/*
| Exercise question types (ARCHITECTURE D31): file validation, compiling into the learner's
| payload and the hidden key, response shapes, and all-or-nothing grading.
*/

beforeEach(function () {
    $this->types = new QuestionTypes;
});

/** A valid file entry of each type. */
function entries(): array
{
    return [
        'single_choice' => ['type' => 'single_choice', 'options' => ['A', 'B', 'C'], 'answer' => 1],
        'scenario' => ['type' => 'scenario', 'scenario' => 'Something happened.', 'options' => ['A', 'B'], 'answer' => 0],
        'multi_choice' => ['type' => 'multi_choice', 'options' => ['A', 'B', 'C', 'D'], 'answer' => [2, 0]],
        'true_false' => ['type' => 'true_false', 'answer' => false],
        'fill_blank' => ['type' => 'fill_blank', 'text' => 'A ___ and a ___.', 'answers' => [['frame'], ['packet', 'datagram']]],
        'matching' => ['type' => 'matching', 'pairs' => [['HTTP', '7'], ['TCP', '4'], ['IP', '3'], ['Ethernet', '2']]],
        'ordering' => ['type' => 'ordering', 'items' => ['one', 'two', 'three', 'four', 'five']],
    ];
}

it('accepts a valid entry of every type and grades its correct response as correct', function (string $type) {
    $entry = entries()[$type];
    expect($this->types->validate($entry))->toBe([]);

    $compiled = $this->types->compile("lesson/{$type}", $entry);
    $kind = Type::from($type);
    $response = $this->types->correctResponse($kind, $compiled['answer']);

    expect($this->types->responseError($kind, $compiled['payload'], $response))->toBeNull()
        ->and($this->types->isCorrect($kind, $compiled['answer'], $response))->toBeTrue()
        ->and($this->types->isCorrect($kind, $compiled['answer'], null))->toBeFalse();
})->with(array_keys(entries()));

it('never puts the key in the payload', function () {
    $compiled = $this->types->compile('lesson/single', entries()['single_choice']);
    expect($compiled['payload'])->toBe(['options' => [['id' => 0, 'label' => 'A'], ['id' => 1, 'label' => 'B'], ['id' => 2, 'label' => 'C']]])
        ->and($compiled['answer'])->toBe(['option' => 1])
        ->and($this->types->compile('lesson/multi', entries()['multi_choice'])['answer'])->toBe(['options' => [0, 2]]);
});

it('shows matching and ordering items shuffled, never already in the right order, and the same on every load', function (string $type, string $list, string $key) {
    $first = $this->types->compile("lesson/{$type}", entries()[$type]);
    $again = $this->types->compile("lesson/{$type}", entries()[$type]);
    $identity = range(0, count($first['answer'][$key]) - 1);

    expect($first)->toEqual($again)
        ->and($first['answer'][$key])->not->toBe($identity)
        ->and(array_column($first['payload'][$list], 'id'))->toBe($identity);
})->with([['matching', 'right', 'matches'], ['ordering', 'items', 'order']]);

it('pairs each matching left item with the right label it was written with', function () {
    $pairs = entries()['matching']['pairs'];
    $compiled = $this->types->compile('lesson/m', entries()['matching']);
    $right = array_column($compiled['payload']['right'], 'label', 'id');

    foreach ($compiled['answer']['matches'] as $left => $rightId) {
        expect($right[$rightId])->toBe($pairs[$left][1]);
    }
});

it('grades all or nothing', function () {
    $m = $this->types->compile('lesson/m', entries()['multi_choice']);
    $f = $this->types->compile('lesson/f', entries()['fill_blank']);

    expect($this->types->isCorrect(Type::MultiChoice, $m['answer'], ['options' => [0]]))->toBeFalse()
        ->and($this->types->isCorrect(Type::MultiChoice, $m['answer'], ['options' => [2, 0]]))->toBeTrue()
        ->and($this->types->isCorrect(Type::MultiChoice, $m['answer'], ['options' => [0, 2, 3]]))->toBeFalse()
        ->and($this->types->isCorrect(Type::FillBlank, $f['answer'], ['blanks' => ['frame', 'segment']]))->toBeFalse();
});

it('matches blanks ignoring case and extra spaces, against any accepted text', function () {
    $f = $this->types->compile('lesson/f', entries()['fill_blank']);

    expect($this->types->isCorrect(Type::FillBlank, $f['answer'], ['blanks' => ['  FRAME ', 'Datagram']]))->toBeTrue()
        ->and(QuestionTypes::normalize("  Two   words\t"))->toBe('two words');
});

it('rejects responses of the wrong shape', function (string $type, mixed $response) {
    $compiled = $this->types->compile("lesson/{$type}", entries()[$type]);

    expect($this->types->responseError(Type::from($type), $compiled['payload'], $response))->toBeString();
})->with([
    ['single_choice', ['option' => 9]],
    ['single_choice', 'B'],
    ['multi_choice', ['options' => [0, 0]]],
    ['true_false', ['value' => 'yes']],
    ['fill_blank', ['blanks' => ['only one']]],
    ['fill_blank', ['blanks' => [str_repeat('a', 101), 'b']]],
    ['matching', ['matches' => [0, 1]]],
    ['ordering', ['order' => [0, 1, 2, 3, 3]]],
]);

it('accepts an unanswered question and a partly matched matching question', function () {
    $compiled = $this->types->compile('lesson/m', entries()['matching']);

    expect($this->types->responseError(Type::Matching, $compiled['payload'], null))->toBeNull()
        ->and($this->types->responseError(Type::Matching, $compiled['payload'], ['matches' => [0, null, null, null]]))->toBeNull();
});

it('reports what is wrong with an entry', function (array $entry, string $message) {
    expect(implode(' | ', $this->types->validate($entry)))->toContain($message);
})->with([
    [['type' => 'essay'], 'type must be one of'],
    [['type' => 'single_choice', 'options' => ['A'], 'answer' => 0], 'options must be a list of 2 to 6'],
    [['type' => 'single_choice', 'options' => ['A', 'A'], 'answer' => 0], 'different from each other'],
    [['type' => 'single_choice', 'options' => ['A', 'B'], 'answer' => 2], 'answer must be the index'],
    [['type' => 'scenario', 'options' => ['A', 'B'], 'answer' => 0], 'scenario is required'],
    [['type' => 'multi_choice', 'options' => ['A', 'B', 'C'], 'answer' => []], 'different option indexes'],
    [['type' => 'true_false', 'answer' => 1], 'true or false'],
    [['type' => 'fill_blank', 'text' => 'No blank here.', 'answers' => []], 'contain 1 to 4 blanks'],
    [['type' => 'fill_blank', 'text' => 'One ___ blank.', 'answers' => [['a'], ['b']]], 'accepted texts for each blank'],
    [['type' => 'matching', 'pairs' => [['A', '1'], ['B', '2']]], 'left items must be a list of 3 to 6'],
    [['type' => 'ordering', 'items' => ['a', 'b']], 'items must be a list of 3 to 7'],
]);
