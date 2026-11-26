<?php

use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\User;
use App\Modules\Labs\Services\LabDefinition;
use App\Modules\Labs\Services\LabDefinitionValidator;
use App\Modules\Labs\Services\LabScenarios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

/*
| The evidence-review engine and the `conclusion` checkpoint (F7 Part D, D80): marks and their
| author-set cap, a verdict with cited evidence, options in an order of their own, and a debrief
| that is written when the attempt first completes and kept until it starts over.
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

/** The marks of the first reference solution, without the answer and verdict. */
function erEvidence(): array
{
    return [erMark('message', 'raw', 4), erMark('message', 'raw', 6), erMark('message', 'client', 5), erMark('message', 'raw', 15)];
}

const ER_DOMAIN = ['type' => 'answer', 'checkpoint' => 'real-domain', 'value' => 'larkfield-secure.example.net'];
const ER_VERDICT = ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'spoofed-sender', 'cited' => ['message:raw:4', 'message:raw:6', 'message:client:5']];

/** @return array<string, mixed> */
function erComplete(): array
{
    return erRun(erStart(), [...erEvidence(), ER_DOMAIN, ER_VERDICT]);
}

it('keeps the indicators, the answer and the explanations off the wire until the lab is complete', function () {
    $secrets = ['dmarc-fail', 'DMARC fails on alignment', 'explanation', '"indicators"', '"evidence"', '"answer":', 'look-alike of the From domain', 'min_cited', 'double extension', '"tries"'];
    $this->actingAs($this->user, 'web');
    $before = $this->getJson('/api/v1/labs/'.ER_LAB)->assertOk()->getContent();
    $attempt = erStart();
    $partial = erRun($attempt, array_slice(erEvidence(), 0, 2));
    // A wrong verdict is counted but says nothing about why.
    $wrong = erAct($partial, ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'fix-spf', 'cited' => ['message:raw:4']])->assertOk()->json('data');

    foreach ([$before, json_encode($attempt), json_encode($partial), json_encode($wrong)] as $json) {
        foreach ($secrets as $secret) {
            expect($json)->not->toContain($secret);
        }
    }
    expect($partial['attempt']['debrief'])->toBeNull()
        ->and($wrong['attempt']['debrief'])->toBeNull()
        ->and(collect($partial['checkpoints'])->firstWhere('id', 'verdict'))->not->toHaveKeys(['answer', 'evidence', 'explanation']);
    // The lines are public (it is the evidence the learner reads); which of them matter is not.
    expect($attempt['scenario']['artifacts'][0]['views'][0]['lines'][3])->toContain('spf=pass');
});

it('completes with the reference solution', function () {
    $data = erComplete();

    expect($data['attempt']['completed_at'])->not->toBeNull()
        ->and(collect($data['checkpoints'])->every(fn ($c) => $c['passed']))->toBeTrue();
});

it('does not pass when every line is marked, and the cap is the author\'s own number', function () {
    $attempt = erStart();
    $cap = collect($attempt['scenario']['artifacts'])->pluck('max_marks', 'id')->all();
    expect($cap)->toBe(['message' => 12, 'mail-log' => 6]);
    // The cap is not what the indicators point to (8 lines in the message, 3 in the log), so it does not say how many to mark.
    $points = [];
    foreach (Lab::query()->where('slug', ER_LAB)->value('scenario')['indicators'] as $indicator) {
        foreach ($indicator['lines'] as $ref) {
            $points[$ref['artifact'].':'.$ref['view'].':'.$ref['line']] = $ref['artifact'];
        }
    }
    expect(array_count_values($points))->toBe(['message' => 8, 'mail-log' => 3]);

    $marks = [];
    $keep = ['message:raw:4', 'message:raw:6', 'message:client:5', 'message:raw:15'];
    $unmark = [];
    foreach ($attempt['scenario']['artifacts'] as $artifact) {
        foreach ($artifact['views'] as $view) {
            foreach (array_keys($view['lines']) as $n) {
                $marks[] = erMark($artifact['id'], $view['id'], $n + 1);
                if (! in_array("{$artifact['id']}:{$view['id']}:".($n + 1), $keep, true)) {
                    $unmark[] = erMark($artifact['id'], $view['id'], $n + 1, false);
                }
            }
        }
    }
    $data = erRun($attempt, [...$marks, ER_DOMAIN, ER_VERDICT]);

    expect(collect($data['checkpoints'])->firstWhere('id', 'mark-evidence')['passed'])->toBeFalse()
        ->and($data['attempt']['completed_at'])->toBeNull();

    // Unmarking down to the evidence passes it: the task checks the current marks.
    $data = erRun($data, $unmark);
    expect(collect($data['checkpoints'])->firstWhere('id', 'mark-evidence')['passed'])->toBeTrue();
});

it('refuses a malformed mark with 422', function (array $action) {
    $this->actingAs($this->user, 'web');
    erAct(erStart(), $action)->assertStatus(422);
})->with([
    'no such artifact' => [erMark('nowhere', 'raw', 1)],
    'no such view' => [erMark('message', 'nowhere', 1)],
    'line past the end' => [erMark('message', 'raw', 99)],
    'line zero' => [erMark('message', 'raw', 0)],
    'on is not a boolean' => [['type' => 'mark', 'artifact' => 'message', 'view' => 'raw', 'line' => 1, 'on' => 'yes']],
    'unknown action' => [['type' => 'delete']],
]);

describe('the conclusion checkpoint', function () {
    it('needs the right verdict and evidence the learner has marked', function () {
        $data = erRun(erStart(), array_slice(erEvidence(), 0, 2));
        $verdict = fn (array $over) => ['type' => 'conclude', 'checkpoint' => 'verdict'] + $over;

        // The right verdict citing something that is not marked, then citing nothing, then the wrong verdict, then an item that does not exist.
        foreach ([
            ['option' => 'spoofed-sender', 'cited' => ['message:client:5']],
            ['option' => 'spoofed-sender', 'cited' => []],
            ['option' => 'allow-list', 'cited' => ['message:raw:4', 'message:raw:6']],
            ['option' => 'spoofed-sender', 'cited' => ['message:raw:4', 'message:raw:6', 'nowhere']],
        ] as $wrong) {
            $data = erAct($data, $verdict($wrong))->assertOk()->json('data');
            expect($data['output']['correct'])->toBeFalse();
        }
        $data = erAct($data, $verdict(['option' => 'spoofed-sender', 'cited' => ['message:raw:4', 'message:raw:6']]))->assertOk()->json('data');
        expect($data['output']['correct'])->toBeTrue()
            ->and(collect($data['checkpoints'])->firstWhere('id', 'verdict')['passed'])->toBeTrue();
    });

    it('lists only what the learner has marked as citable, never the rest', function () {
        $data = erRun(erStart(), [erMark('message', 'raw', 5), erMark('mail-log', 'log', 2)]);
        $citable = collect($data['checkpoints'])->firstWhere('id', 'verdict')['citable'];

        expect(array_column($citable, 'id'))->toBe(['message:raw:5', 'mail-log:log:2'])
            ->and($citable[0]['label'])->toContain('Larkfield Savings Support');
    });

    it('refuses an unknown option or checkpoint with 422, without counting it', function () {
        $this->actingAs($this->user, 'web');
        $attempt = erStart();
        erAct($attempt, ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'nope', 'cited' => []])->assertStatus(422);
        erAct($attempt, ['type' => 'conclude', 'checkpoint' => 'nope', 'option' => 'spoofed-sender', 'cited' => []])->assertStatus(422);
        erAct($attempt, ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'spoofed-sender', 'cited' => 'all'])->assertStatus(422);

        expect(LabAttempt::query()->find($attempt['attempt']['id'])->state['tries'])->toBe([]);
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
        $data = erRun($attempt, [...erEvidence(), erMark('message', 'raw', 11), ER_DOMAIN, ER_VERDICT]);
        $debrief = $data['attempt']['debrief'];

        expect($data['attempt']['completed_at'])->not->toBeNull()
            ->and(collect($debrief['indicators'])->pluck('found', 'id')->all())->toBe(['dmarc-fail' => true, 'reply-to-differs' => true, 'link-target' => true, 'attachment-name' => true, 'origin-host' => false, 'delivered-anyway' => false])
            ->and(collect($debrief['indicators'])->firstWhere('id', 'origin-host'))->toMatchArray(['required' => false, 'found' => false])
            ->and(collect($debrief['indicators'])->firstWhere('id', 'attachment-name')['explanation'])->toContain('declared type')
            ->and($debrief['wrong'])->toBe([['artifact' => 'The message', 'view' => 'Raw message', 'line' => 11, 'text' => 'X-Mailer: BulkSend 2.1']])
            ->and($debrief['conclusions'][0])->toMatchArray(['checkpoint' => 'verdict', 'tries' => 1]);
        // The next read of the lab carries it too.
        expect($this->getJson('/api/v1/labs/'.ER_LAB)->json('data.attempt.debrief.indicators'))->toHaveCount(6);
    });

    it('is written when the attempt first completes and kept when work is undone afterwards', function () {
        $data = erComplete();
        $first = $data['attempt']['debrief'];
        // Unmark an indicator's line and the task fails again, but the debrief is the one earned.
        $data = erAct($data, erMark('message', 'raw', 4, false))->assertOk()->json('data');

        expect(collect($data['checkpoints'])->firstWhere('id', 'mark-evidence')['passed'])->toBeFalse()
            ->and($data['attempt']['debrief'])->toEqual($first)
            ->and(collect($data['attempt']['debrief']['indicators'])->firstWhere('id', 'dmarc-fail')['found'])->toBeTrue()
            ->and($this->getJson('/api/v1/labs/'.ER_LAB)->json('data.attempt.debrief'))->toEqual($first);
    });

    it('is gone after a reset, even though completion is kept, and is earned again by completing again', function () {
        $data = erComplete();
        $reset = $this->postJson("/api/v1/lab-attempts/{$data['attempt']['id']}/reset", ['version' => $data['attempt']['version']])->assertOk()->json('data');

        expect($reset['attempt']['completed_at'])->not->toBeNull()
            ->and($reset['attempt']['debrief'])->toBeNull()
            ->and(LabAttempt::query()->find($data['attempt']['id'])->debrief)->toBeNull()
            ->and(json_encode($reset))->not->toContain('DMARC fails on alignment');

        $again = erRun($reset, [...erEvidence(), ER_DOMAIN, ER_VERDICT]);
        expect($again['attempt']['debrief']['conclusions'][0]['tries'])->toBe(1);
    });

    it('says how many tries the verdict took: every submission counts, none is blocked, and a passed one stops counting', function () {
        $data = erRun(erStart(), array_slice(erEvidence(), 0, 3));
        foreach (['fix-spf', 'compromised'] as $wrong) {
            $data = erAct($data, ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => $wrong, 'cited' => ['message:raw:4']])->assertOk()->json('data');
        }
        $data = erRun($data, [erMark('message', 'raw', 15), ER_DOMAIN, ER_VERDICT]);
        // Submitting again after it passed is allowed and changes nothing.
        $data = erAct($data, ER_VERDICT)->assertOk()->json('data');

        expect($data['attempt']['debrief']['conclusions'][0]['tries'])->toBe(3);
    });

    it('starts over for an attempt a published change restarted', function () {
        $data = erComplete();
        LabAttempt::query()->whereKey($data['attempt']['id'])->update(['scenario_version' => 99]);

        $reloaded = $this->getJson('/api/v1/labs/'.ER_LAB)->assertOk()->json('data');
        expect($reloaded['attempt']['debrief'])->toBeNull()->and($reloaded['attempt']['restarted'])->toBeTrue();
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

    it('requires an author-set cap on marks that covers the lines the indicators point to', function () {
        $def = erDef('evidence-review');
        unset($def['scenario']['artifacts'][0]['max_marks']);
        expect(array_keys(app(LabDefinitionValidator::class)->check($def)['errors']))->toContain('scenario.artifacts.0.max_marks');

        $def = erDef('evidence-review', 'code-review');
        $def['scenario']['artifacts'][0]['max_marks'] = 2;
        expect(app(LabDefinitionValidator::class)->check($def)['errors']['scenario.artifacts.0.max_marks'][0])->toContain('at least the number of lines');
        $def['scenario']['artifacts'][0]['max_marks'] = 3;
        expect(app(LabDefinitionValidator::class)->check($def)['errors'])->not->toHaveKey('scenario.artifacts.0.max_marks');
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
        $def['scenario']['artifacts'] = array_map(fn ($n) => ['id' => "a{$n}", 'title' => 'A', 'max_marks' => 5, 'views' => [['id' => 'v', 'label' => 'V', 'lines' => $lines]]], range(1, 4));
        $def['scenario']['indicators'][0]['lines'][0] = ['artifact' => 'a1', 'view' => 'v', 'line' => 1];

        expect(app(LabDefinitionValidator::class)->check($def)['errors'])->toHaveKey('scenario.artifacts');
    });
});
