<?php

use App\Models\Lab;
use App\Models\User;
use App\Modules\Labs\Services\LabDefinition;
use App\Modules\Labs\Services\LabDefinitionValidator;
use App\Modules\Labs\Services\LabScenarios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

/*
| The evidence-review engine and the `conclusion` checkpoint (F7 Part D, D80): marks and their
| cap, a verdict with cited evidence, options in an order of their own, and a debrief that exists
| only once the lab is complete.
*/

const ER_LAB = 'spot-the-phishing-email';

beforeEach(function () {
    $this->user = User::factory()->create();
});

/** @return array<string, mixed> */
function erStart(): array
{
    return test()->actingAs(test()->user, 'web')->postJson('/api/v1/labs/'.ER_LAB.'/attempts')->assertSuccessful()->json('data');
}

/** @param  array<string, mixed>  $data  a lab's data: what the start and every action return */
function erAct(array $data, array $action): TestResponse
{
    return test()->postJson("/api/v1/lab-attempts/{$data['attempt']['id']}/actions", ['version' => $data['attempt']['version'], 'action' => $action]);
}

/** Runs actions in turn, each with the version the last one returned. @return array<string, mixed> the last response's data */
function erRun(array $data, array $actions): array
{
    foreach ($actions as $action) {
        $data = erAct($data, $action)->assertOk()->json('data');
    }

    return $data;
}

function erMark(string $artifact, string $view, int $line, bool $on = true): array
{
    return ['type' => 'mark', 'artifact' => $artifact, 'view' => $view, 'line' => $line, 'on' => $on];
}

/** The marks of the reference solution, without the answer and verdict. */
function erEvidence(): array
{
    return [erMark('email', 'raw', 3), erMark('email', 'raw', 5), erMark('email', 'rendered', 7), erMark('email', 'rendered', 3)];
}

const ER_VERDICT = ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'phishing', 'cited' => ['email:raw:3', 'email:raw:5', 'email:rendered:7']];

it('keeps the indicators, the answer and the explanations off the wire until the lab is complete', function () {
    $secrets = ['sender-not-authorized', 'The sender checks failed', 'explanation', '"indicators"', '"evidence"', '"answer":', 'look-alike domain', 'min_cited'];
    $this->actingAs($this->user, 'web');
    $before = $this->getJson('/api/v1/labs/'.ER_LAB)->assertOk()->getContent();
    $attempt = erStart();
    $partial = erRun($attempt, array_slice(erEvidence(), 0, 2));

    foreach ([$before, json_encode($attempt), json_encode($partial)] as $json) {
        foreach ($secrets as $secret) {
            expect($json)->not->toContain($secret);
        }
    }
    expect($partial['attempt']['debrief'])->toBeNull()
        ->and(collect($partial['checkpoints'])->firstWhere('id', 'verdict'))->not->toHaveKeys(['answer', 'evidence', 'explanation']);
    // The lines are public (it is the evidence the learner reads); which of them matter is not.
    expect($attempt['scenario']['artifacts'][0]['views'][0]['lines'][2])->toContain('spf=fail');
});

it('completes with the reference solution and counts the first completion once', function () {
    $attempt = erStart();
    $data = erRun($attempt, [...erEvidence(), ['type' => 'answer', 'checkpoint' => 'real-domain', 'value' => 'larkfield-secure.example.net'], ER_VERDICT]);

    expect($data['attempt']['completed_at'])->not->toBeNull()
        ->and(collect($data['checkpoints'])->every(fn ($c) => $c['passed']))->toBeTrue();
});

it('does not pass when every line is marked, and the cap is public', function () {
    $attempt = erStart();
    $cap = collect($attempt['scenario']['artifacts'])->pluck('max_marks', 'id')->all();
    // Four lines of evidence in the email's views (five with the bonus one), plus two extra.
    expect($cap)->toBe(['email' => 8, 'mail-log' => 4]);

    $marks = [];
    foreach ($attempt['scenario']['artifacts'] as $artifact) {
        foreach ($artifact['views'] as $view) {
            foreach (array_keys($view['lines']) as $n) {
                $marks[] = erMark($artifact['id'], $view['id'], $n + 1);
            }
        }
    }
    $data = erRun($attempt, [...$marks, ['type' => 'answer', 'checkpoint' => 'real-domain', 'value' => 'larkfield-secure.example.net'], ER_VERDICT]);

    expect(collect($data['checkpoints'])->firstWhere('id', 'mark-evidence')['passed'])->toBeFalse()
        ->and($data['attempt']['completed_at'])->toBeNull();

    // Unmarking down to the evidence passes it again: the check is on the current marks.
    $unmark = [];
    foreach ($attempt['scenario']['artifacts'] as $artifact) {
        foreach ($artifact['views'] as $view) {
            foreach (array_keys($view['lines']) as $n) {
                if (! in_array("{$artifact['id']}:{$view['id']}:".($n + 1), ['email:raw:3', 'email:raw:5', 'email:rendered:7', 'email:rendered:3', 'mail-log:log:2'], true)) {
                    $unmark[] = erMark($artifact['id'], $view['id'], $n + 1, false);
                }
            }
        }
    }
    $data = erRun($data, $unmark);
    expect(collect($data['checkpoints'])->firstWhere('id', 'mark-evidence')['passed'])->toBeTrue();
});

it('refuses a malformed mark with 422', function (array $action) {
    $this->actingAs($this->user, 'web');
    erAct(erStart(), $action)->assertStatus(422);
})->with([
    'no such artifact' => [erMark('nowhere', 'raw', 1)],
    'no such view' => [erMark('email', 'nowhere', 1)],
    'line past the end' => [erMark('email', 'raw', 99)],
    'line zero' => [erMark('email', 'raw', 0)],
    'on is not a boolean' => [['type' => 'mark', 'artifact' => 'email', 'view' => 'raw', 'line' => 1, 'on' => 'yes']],
    'unknown action' => [['type' => 'delete']],
]);

describe('the conclusion checkpoint', function () {
    it('needs the right verdict and evidence the learner has marked', function () {
        $attempt = erStart();
        $data = erRun($attempt, array_slice(erEvidence(), 0, 2));
        $verdict = fn (array $over) => ['type' => 'conclude', 'checkpoint' => 'verdict'] + $over;

        // The right verdict citing something that is not marked, then citing nothing, then the wrong verdict.
        foreach ([
            ['option' => 'phishing', 'cited' => ['email:rendered:7']],
            ['option' => 'phishing', 'cited' => []],
            ['option' => 'real-bank', 'cited' => ['email:raw:3', 'email:raw:5']],
            ['option' => 'phishing', 'cited' => ['email:raw:3', 'email:raw:5', 'email:raw:3', 'email:raw:5', 'email:raw:5', 'nowhere']],
        ] as $wrong) {
            $data = erAct($data, $verdict($wrong))->assertOk()->json('data');
            expect($data['output']['correct'])->toBeFalse();
        }
        $data = erAct($data, $verdict(['option' => 'phishing', 'cited' => ['email:raw:3', 'email:raw:5']]))->assertOk()->json('data');
        expect($data['output']['correct'])->toBeTrue()
            ->and(collect($data['checkpoints'])->firstWhere('id', 'verdict')['passed'])->toBeTrue();
    });

    it('lists only what the learner has marked as citable, never the rest', function () {
        $attempt = erStart();
        $data = erRun($attempt, [erMark('email', 'raw', 4), erMark('mail-log', 'log', 2)]);
        $citable = collect($data['checkpoints'])->firstWhere('id', 'verdict')['citable'];

        expect(array_column($citable, 'id'))->toBe(['email:raw:4', 'mail-log:log:2'])
            ->and($citable[0]['label'])->toContain('Larkfield Savings Support');
    });

    it('refuses an unknown option or checkpoint with 422', function () {
        $this->actingAs($this->user, 'web');
        $attempt = erStart();
        erAct($attempt, ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'nope', 'cited' => []])->assertStatus(422);
        erAct($attempt, ['type' => 'conclude', 'checkpoint' => 'nope', 'option' => 'phishing', 'cited' => []])->assertStatus(422);
        erAct($attempt, ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'phishing', 'cited' => 'all'])->assertStatus(422);
    });

    it('shows the options in an order of the attempt, the same on reload and reset, and not the authored one', function () {
        $authored = array_column(collect(Lab::query()->where('slug', ER_LAB)->value('scenario')['checkpoints'])->firstWhere('id', 'verdict')['options'], 'id');
        $order = fn (array $data) => array_column(collect($data['checkpoints'])->firstWhere('id', 'verdict')['options'], 'id');

        $orders = [];
        foreach (range(1, 6) as $_) {
            $learner = User::factory()->create();
            Auth::forgetGuards();
            $attempt = $this->actingAs($learner, 'web')->postJson('/api/v1/labs/'.ER_LAB.'/attempts')->json('data');
            $reload = $this->getJson('/api/v1/labs/'.ER_LAB)->json('data');
            $reset = $this->postJson("/api/v1/lab-attempts/{$attempt['attempt']['id']}/reset", ['version' => $attempt['attempt']['version']])->assertOk()->json('data');
            $orders[] = $order($attempt);

            expect($order($attempt))->toEqualCanonicalizing($authored)
                ->and($order($reload))->toBe($order($attempt))
                ->and($order($reset))->toBe($order($attempt));
        }
        // Across attempts the order varies, so a position says nothing about the answer.
        expect(array_unique(array_map('json_encode', $orders)))->not->toHaveCount(1);
    });

    it('does not shuffle the options of an attempt that has not begun', function () {
        $data = $this->actingAs($this->user, 'web')->getJson('/api/v1/labs/'.ER_LAB)->json('data');

        expect(collect($data['checkpoints'])->firstWhere('id', 'verdict'))->not->toHaveKey('options');
    });
});

describe('the debrief', function () {
    it('holds the indicators with their explanations, what was missed and what was marked wrongly, once complete', function () {
        $attempt = erStart();
        $data = erRun($attempt, [...erEvidence(), erMark('email', 'raw', 10), ['type' => 'answer', 'checkpoint' => 'real-domain', 'value' => 'larkfield-secure.example.net'], ER_VERDICT]);
        $debrief = $data['attempt']['debrief'];

        expect($data['attempt']['completed_at'])->not->toBeNull()
            ->and(collect($debrief['indicators'])->pluck('found', 'id')->all())->toBe(['sender-not-authorized' => true, 'reply-elsewhere' => true, 'link-mismatch' => true, 'pressure' => true, 'return-path' => false])
            ->and(collect($debrief['indicators'])->firstWhere('id', 'return-path'))->toMatchArray(['required' => false, 'found' => false])
            ->and(collect($debrief['indicators'])->firstWhere('id', 'pressure')['explanation'])->toContain('deadline')
            ->and($debrief['wrong'])->toBe([['artifact' => 'The email', 'view' => 'Raw headers', 'line' => 10, 'text' => 'X-Mailer: BulkSend 2.1']])
            ->and($debrief['conclusions'][0]['checkpoint'])->toBe('verdict');
        // The next read of the lab carries it too.
        expect($this->getJson('/api/v1/labs/'.ER_LAB)->json('data.attempt.debrief.indicators'))->toHaveCount(5);
    });

    it('is gone after a reset, even though completion is kept', function () {
        $attempt = erStart();
        $data = erRun($attempt, [...erEvidence(), ['type' => 'answer', 'checkpoint' => 'real-domain', 'value' => 'larkfield-secure.example.net'], ER_VERDICT]);
        $reset = $this->postJson("/api/v1/lab-attempts/{$attempt['attempt']['id']}/reset", ['version' => $data['attempt']['version']])->assertOk()->json('data');

        expect($reset['attempt']['completed_at'])->not->toBeNull()
            ->and($reset['attempt']['debrief'])->toBeNull()
            ->and(json_encode($reset))->not->toContain('The sender checks failed');
    });
});

describe('the definition', function () {
    function erDef(string $engine, ?string $fixture = null): array
    {
        $starter = app(LabScenarios::class)->starter($engine);
        if ($fixture) {
            $starter = json_decode((string) file_get_contents(LabScenarios::directory()."/fixtures/{$engine}/{$fixture}.json"), true);
        }

        return ['title' => 'A lab', 'summary' => 'S.', 'briefing' => 'B.', 'domain' => 'linux', 'lesson' => null, 'engine' => $engine, 'difficulty' => 1, 'estimated_minutes' => 5, 'scenario' => $starter['scenario'], 'solutions' => $starter['solutions']];
    }

    $conclusion = fn (array $over = []) => $over + [
        'id' => 'verdict', 'kind' => 'conclusion', 'title' => 'Conclude.', 'question' => 'Which?',
        'options' => [['id' => 'yes', 'text' => 'Yes'], ['id' => 'no', 'text' => 'No']], 'answer' => 'yes', 'evidence' => ['rule-1'],
    ];

    it('accepts the code-review variant and replays it', function () {
        $report = app(LabDefinitionValidator::class)->check(erDef('evidence-review', 'code-review'), replay: true);

        expect($report['errors'])->toBe([]);
    });

    it('lets the firewall and the HTTP inspector opt in to a conclusion through citableIds()', function () use ($conclusion) {
        $fw = erDef('firewall-rules');
        $fw['scenario']['checkpoints'][] = $conclusion();
        $fw['solutions']['solutions'][0]['actions'][] = ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'yes', 'cited' => ['rule-1']];
        $fw['solutions']['solutions'][1]['actions'][] = ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'yes', 'cited' => ['rule-1']];
        expect(app(LabDefinitionValidator::class)->check($fw, replay: true)['errors'])->toBe([]);

        $http = erDef('http-inspector');
        $http['scenario']['checkpoints'][] = $conclusion(['evidence' => ['exchange-1']]);
        $http['solutions']['solutions'][0]['actions'][] = ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'yes', 'cited' => ['exchange-1']];
        expect(app(LabDefinitionValidator::class)->check($http, replay: true)['errors'])->toBe([]);
    });

    it('names what is wrong with a conclusion', function (array $over, string $field) use ($conclusion) {
        $def = erDef('firewall-rules');
        $def['scenario']['checkpoints'][] = $conclusion($over);

        expect(array_keys(app(LabDefinitionValidator::class)->check($def)['errors']))->toContain("scenario.checkpoints.1.{$field}");
    })->with([
        'answer not an option' => [['answer' => 'maybe'], 'answer'],
        'evidence the engine does not offer' => [['evidence' => ['rule-9']], 'evidence'],
        'duplicate option ids' => [['options' => [['id' => 'yes', 'text' => 'A'], ['id' => 'yes', 'text' => 'B']]], 'options'],
        'min above the evidence' => [['min_cited' => 3], 'min_cited'],
    ]);

    it('refuses a maximum below the least a learner must cite', function () use ($conclusion) {
        $def = erDef('evidence-review');
        $def['scenario']['checkpoints'][] = $conclusion(['evidence' => ['message:text:1', 'message:text:2'], 'min_cited' => 2, 'max_cited' => 1]);

        expect(array_keys(app(LabDefinitionValidator::class)->check($def)['errors']))->toContain('scenario.checkpoints.1.max_cited');
    });

    it('refuses a conclusion in an engine with nothing to cite', function () use ($conclusion) {
        $def = erDef('linux-shell');
        $def['scenario']['checkpoints'][] = $conclusion();

        expect(array_keys(app(LabDefinitionValidator::class)->check($def)['errors']))->toContain('scenario.checkpoints.'.(count($def['scenario']['checkpoints']) - 1).'.kind');
    });

    it('does not restart attempts for a change of words in the indicators or the conclusion', function () {
        $def = erDef('evidence-review');
        $changed = $def;
        $changed['scenario']['indicators'][0]['explanation'] = 'Other words.';
        $changed['scenario']['indicators'][0]['title'] = 'Other title';

        expect(LabDefinition::breakingKey($changed))->toBe(LabDefinition::breakingKey($def));

        $moved = $def;
        $moved['scenario']['indicators'][0]['lines'][0]['line'] = 1;
        expect(LabDefinition::breakingKey($moved))->not->toBe(LabDefinition::breakingKey($def));
    });

    it('rejects a scenario with more lines than the engine allows', function () {
        $def = erDef('evidence-review');
        $lines = array_fill(0, 200, 'x');
        $def['scenario']['artifacts'] = array_map(fn ($n) => ['id' => "a{$n}", 'title' => 'A', 'views' => [['id' => 'v', 'label' => 'V', 'lines' => $lines]]], range(1, 4));
        $def['scenario']['indicators'][0]['lines'][0] = ['artifact' => 'a1', 'view' => 'v', 'line' => 1];

        expect(app(LabDefinitionValidator::class)->check($def)['errors'])->toHaveKey('scenario.artifacts');
    });
});
