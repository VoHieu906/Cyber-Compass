<?php

use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\User;
use App\Modules\Labs\Engines\IncidentGraph;
use App\Modules\Labs\Services\LabDefinition;
use App\Modules\Labs\Services\LabDefinitionValidator;
use App\Modules\Labs\Services\LabScenarios;
use Illuminate\Testing\TestResponse;

/*
| The incident-response engine (F7 Part E, D80): a data-driven state machine validated as a graph,
| outcome tasks on state so several orders pass, dead ends read from a precomputed table, and a
| debrief of the learner's path.
*/

const IR_LAB = 'contain-a-ransomware-incident';

beforeEach(function () {
    $this->user = User::factory()->create();
});

/** @return array<string, mixed> */
function irStart(): array
{
    return test()->actingAs(test()->user, 'web')->postJson('/api/v1/labs/'.IR_LAB.'/attempts')->assertSuccessful()->json('data');
}

/** @param  array<string, mixed>  $data */
function irAct(array $data, array $action): TestResponse
{
    return test()->postJson("/api/v1/lab-attempts/{$data['attempt']['id']}/actions", ['version' => $data['attempt']['version'], 'action' => $action]);
}

/** @param  list<string>  $choices  @return array<string, mixed> */
function irChoose(array $data, array $choices): array
{
    foreach ($choices as $choice) {
        $data = irAct($data, ['type' => 'choose', 'choice' => $choice])->assertOk()->json('data');
    }

    return $data;
}

const IR_ORDER_A = ['capture-live', 'isolate-port', 'kill-encryptor', 'revoke-sessions', 'rotate-credentials', 'restore-clean'];
const IR_ORDER_B = ['isolate-port', 'kill-encryptor', 'capture-live', 'rotate-credentials', 'revoke-sessions', 'restore-clean'];
const IR_VERDICT = ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'fix-credentials', 'cited' => ['credentials:rotated', 'access:revoked']];

it('starts with the alert, the technical state and the choices, and nothing that was not earned', function () {
    $data = irStart();
    $view = (array) $data['attempt']['state'];

    expect($view['situation'])->toContain('fs-01')
        ->and($view['ended'])->toBeFalse()
        ->and($view['timeline'])->toBe([])
        ->and($view['dead_end'])->toBeNull()
        ->and(collect($view['vars'])->pluck('value_label', 'id')->all())->toBe(['network' => 'Connected', 'encryption' => 'Running', 'evidence' => 'At risk', 'access' => 'Active', 'credentials' => 'Compromised', 'shares' => 'Up, being encrypted'])
        ->and(collect($view['choices'])->every(fn ($c) => array_keys($c) === ['id', 'label', 'available']))->toBeTrue()
        // The state a restore needs is not met, so it shows as unavailable, with no reason.
        ->and(collect($view['choices'])->firstWhere('id', 'restore-clean')['available'])->toBeFalse()
        ->and($data['scenario'])->toHaveKey('variables')->not->toHaveKey('nodes');
});

it('keeps the trade-offs, effects, requirements and the attacker\'s next moves off the wire until they are earned', function () {
    $secrets = ['Preserves volatile evidence', 'tradeoff', '"effects"', '"requires"', 'reinfected', 'starts encrypting the restored files', 'Cut off from fs-01', 'keeps_open'];
    $this->actingAs($this->user, 'web');
    $before = $this->getJson('/api/v1/labs/'.IR_LAB)->assertOk()->getContent();
    $started = irStart();
    $partial = irChoose($started, ['capture-live']);

    foreach ([$before, json_encode($started)] as $json) {
        foreach ($secrets as $secret) {
            expect($json)->not->toContain($secret);
        }
    }
    // After a choice the learner sees what it did and the attacker's reply to it, not the others'.
    $json = json_encode($partial);
    expect($json)->toContain('While the capture runs')->not->toContain('Cut off from fs-01')->not->toContain('tradeoff')->not->toContain('Preserves volatile evidence')
        ->and($partial['attempt']['debrief'])->toBeNull();
});

it('applies a choice: the effect on the state, the consequence and the attacker\'s next move', function () {
    $data = irAct(irStart(), ['type' => 'choose', 'choice' => 'isolate-port'])->assertOk()->json('data');
    $view = (array) $data['attempt']['state'];

    expect($data['output']['consequence'])->toContain('outbound connection')
        ->and($data['output']['attacker'])->toContain('fs-02')
        ->and($data['output']['changes'])->toBe([['label' => 'fs-01 network', 'from' => 'Connected', 'to' => 'Isolated']])
        ->and($view['situation'])->toBe($data['output']['attacker'])
        ->and($view['timeline'])->toHaveCount(1)
        ->and($view['timeline'][0]['decision'])->toContain('switch port')
        ->and(collect($view['vars'])->firstWhere('id', 'network')['value'])->toBe('isolated')
        // Isolation is done, so it cannot be chosen again.
        ->and(collect($view['choices'])->firstWhere('id', 'isolate-port')['available'])->toBeFalse();
});

it('refuses a choice that is not available, unknown, or taken after the end, with 422', function () {
    $this->actingAs($this->user, 'web');
    $data = irStart();
    irAct($data, ['type' => 'choose', 'choice' => 'restore-clean'])->assertStatus(422);
    irAct($data, ['type' => 'choose', 'choice' => 'nope'])->assertStatus(422);
    irAct($data, ['type' => 'wait'])->assertStatus(422);
    irAct($data, ['type' => 'choose'])->assertStatus(422);

    $ended = irChoose($data, ['restore-now']);
    expect(((array) $ended['attempt']['state'])['ended'])->toBeTrue();
    irAct($ended, ['type' => 'choose', 'choice' => 'isolate-port'])->assertStatus(422);
});

it('completes in more than one order', function (array $order) {
    $data = irChoose(irStart(), $order);
    $data = irAct($data, IR_VERDICT)->assertOk()->json('data');

    expect($data['output']['correct'])->toBeTrue()
        ->and($data['attempt']['completed_at'])->not->toBeNull()
        ->and(collect($data['checkpoints'])->every(fn ($c) => $c['passed']))->toBeTrue();
})->with(['capture first' => [IR_ORDER_A], 'contain first' => [IR_ORDER_B]]);

describe('dead ends', function () {
    it('says so plainly the moment a task can no longer pass, and names no choice', function () {
        $data = irChoose(irStart(), ['power-off']);
        $view = (array) $data['attempt']['state'];

        expect($view['dead_end'])->toBe(['unreachable' => ['evidence-kept']])
            // The incident can still be played on and closed.
            ->and($view['ended'])->toBeFalse()
            ->and(collect($view['choices'])->firstWhere('id', 'revoke-sessions')['available'])->toBeTrue();
        $json = json_encode($view['dead_end']);
        expect($json)->not->toContain('power-off')->not->toContain('capture');
    });

    it('is not shown while every task can still pass, however the incident has gone', function () {
        $data = irChoose(irStart(), ['isolate-port', 'kill-encryptor', 'revoke-sessions']);

        expect(((array) $data['attempt']['state'])['dead_end'])->toBeNull();
    });

    it('names the tasks that are out of reach after a restore into a live attacker, which ends the incident', function () {
        $data = irChoose(irStart(), ['restore-now']);
        $view = (array) $data['attempt']['state'];

        expect($view['ended'])->toBeTrue()
            ->and($view['dead_end']['unreachable'])->toContain('restored', 'cut-off')
            ->and($data['attempt']['completed_at'])->toBeNull();
    });

    it('also catches a path that closes the incident without ever capturing the evidence', function () {
        $data = irChoose(irStart(), ['isolate-port', 'kill-encryptor', 'revoke-sessions', 'rotate-credentials']);
        expect(((array) $data['attempt']['state'])['dead_end'])->toBeNull();

        $data = irChoose($data, ['restore-clean']);
        expect(((array) $data['attempt']['state'])['dead_end']['unreachable'])->toContain('evidence-kept')
            ->and($data['attempt']['completed_at'])->toBeNull();
    });

    it('starting over clears it', function () {
        $data = irChoose(irStart(), ['power-off']);
        $reset = $this->postJson("/api/v1/lab-attempts/{$data['attempt']['id']}/reset", ['version' => $data['attempt']['version']])->assertOk()->json('data');

        expect(((array) $reset['attempt']['state'])['dead_end'])->toBeNull()->and(((array) $reset['attempt']['state'])['timeline'])->toBe([]);
    });

    it('reads the table once per scenario, not once per request', function () {
        $scenario = Lab::query()->where('slug', IR_LAB)->value('scenario');
        $first = IncidentGraph::of($scenario);

        expect(IncidentGraph::of($scenario))->toBe($first)
            ->and(count($first['states']))->toBeLessThan(IncidentGraph::MAX_STATES)
            ->and($first['truncated'])->toBeFalse();
    });
});

describe('the debrief', function () {
    it('lists the learner\'s path with each trade-off and where another choice would have led', function () {
        $data = irChoose(irStart(), ['isolate-port', 'kill-encryptor', 'capture-live', 'rotate-credentials', 'revoke-sessions', 'restore-clean']);
        $data = irAct($data, IR_VERDICT)->assertOk()->json('data');
        $debrief = $data['attempt']['debrief'];

        expect($debrief['path'])->toHaveCount(6)
            ->and($debrief['path'][0]['decision'])->toContain('switch port')
            ->and($debrief['path'][0]['tradeoff'])->toContain('Stops the transfer')
            ->and(collect($debrief['path'][0]['alternatives'])->firstWhere('label', 'Power fs-01 off at once')['keeps_open'])->toBeFalse()
            ->and(collect($debrief['path'][0]['alternatives'])->firstWhere('label', 'Stop the encrypting process through the EDR agent')['keeps_open'])->toBeTrue()
            ->and(collect($debrief['final'])->pluck('value', 'label')->all()['File shares'])->toBe('Restored from backup')
            ->and($debrief['conclusions'][0])->toMatchArray(['checkpoint' => 'verdict', 'tries' => 1]);
        // The alternatives are only the choices that were open at that moment.
        expect(collect($debrief['path'][5]['alternatives'])->pluck('label')->all())->toBe([]);
    });

    it('is kept once earned and gone after a reset', function () {
        $data = irAct(irChoose(irStart(), IR_ORDER_A), IR_VERDICT)->assertOk()->json('data');
        $first = $data['attempt']['debrief'];
        expect($this->getJson('/api/v1/labs/'.IR_LAB)->json('data.attempt.debrief'))->toEqual($first);

        $reset = $this->postJson("/api/v1/lab-attempts/{$data['attempt']['id']}/reset", ['version' => $data['attempt']['version']])->assertOk()->json('data');
        expect($reset['attempt']['debrief'])->toBeNull()->and(LabAttempt::query()->find($data['attempt']['id'])->debrief)->toBeNull();
    });
});

it('offers the current facts as what a conclusion can cite', function () {
    $data = irChoose(irStart(), ['isolate-port']);
    $citable = collect($data['checkpoints'])->firstWhere('id', 'verdict')['citable'];

    expect(array_column($citable, 'id'))->toContain('network:isolated', 'credentials:compromised')->not->toContain('network:connected')
        ->and(collect($citable)->firstWhere('id', 'network:isolated')['label'])->toBe('fs-01 network: Isolated');
});

describe('the definition', function () {
    function irDef(): array
    {
        $starter = app(LabScenarios::class)->starter('incident-response');

        return ['title' => 'A lab', 'summary' => 'S.', 'briefing' => 'B.', 'domain' => 'linux', 'lesson' => null, 'engine' => 'incident-response', 'difficulty' => 1, 'estimated_minutes' => 5, 'scenario' => $starter['scenario'], 'solutions' => $starter['solutions']];
    }

    /** @return list<string> the paths with an error */
    function irErrors(array $def): array
    {
        return array_keys(app(LabDefinitionValidator::class)->check($def)['errors']);
    }

    it('accepts the seeded lab and replays its solutions and decoys', function () {
        $def = LabDefinition::fromLab(Lab::query()->where('slug', IR_LAB)->firstOrFail());

        expect(app(LabDefinitionValidator::class)->check($def, replay: true)['errors'])->toBe([]);
    });

    it('refuses a cycle that never ends', function () {
        $def = irDef();
        // The alert only leads back to itself, whatever is chosen.
        foreach ($def['scenario']['nodes'][0]['choices'] as $i => $choice) {
            $def['scenario']['nodes'][0]['choices'][$i]['next'] = 'alert';
        }

        expect(irErrors($def))->toContain('scenario.nodes.0');
    });

    it('allows a loop that has a way out', function () {
        $def = irDef();
        $def['scenario']['nodes'][0]['choices'][1]['next'] = 'alert';

        expect(irErrors($def))->not->toContain('scenario.nodes.0');
    });

    it('refuses a state with nothing to choose, and a node that is not an ending and has no choices', function () {
        $def = irDef();
        // Both choices need a state that cannot hold once the first is taken, so a state is stuck.
        $def['scenario']['nodes'][0]['choices'][0]['requires'] = [['network' => 'isolated']];
        $def['scenario']['nodes'][0]['choices'][1]['requires'] = [['network' => 'isolated']];
        expect(irErrors($def))->toContain('scenario.nodes.0.choices');

        $def = irDef();
        $def['scenario']['nodes'][1]['ending'] = false;
        expect(irErrors($def))->toContain('scenario.nodes.1.choices');
    });

    it('refuses a node nothing reaches and a task nothing makes true', function () {
        $def = irDef();
        $def['scenario']['nodes'][] = ['id' => 'orphan', 'event' => 'Never reached.', 'ending' => true];
        expect(irErrors($def))->toContain('scenario.nodes.3');

        $def = irDef();
        $def['scenario']['nodes'][0]['choices'][0]['effects'] = [];
        expect(irErrors($def))->toContain('scenario.checkpoints.0');
    });

    it('says when every task can be passed but not all together', function () {
        $def = irDef();
        $def['scenario']['variables'][] = ['id' => 'logs', 'label' => 'Logs', 'initial' => 'kept', 'values' => [['id' => 'kept', 'label' => 'Kept'], ['id' => 'wiped', 'label' => 'Wiped']]];
        $def['scenario']['nodes'][0]['choices'][0]['effects'] = ['network' => 'isolated', 'logs' => 'wiped'];
        // Isolating wipes the logs, and the second task needs the logs kept, which only the other choice leaves.
        $def['scenario']['checkpoints'][] = ['id' => 'kept-logs', 'kind' => 'outcome', 'title' => 'Logs are kept.', 'when' => ['logs' => 'kept'], 'ending' => true];

        expect(irErrors($def))->toContain('scenario.checkpoints');
    });

    it('refuses a scenario with more than 500 reachable states', function () {
        $def = irDef();
        $variables = [];
        $choices = [];
        foreach (range(1, 8) as $n) {
            $variables[] = ['id' => "v{$n}", 'label' => "V{$n}", 'initial' => 'a', 'values' => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']]];
            $choices[] = ['id' => "set-{$n}", 'label' => "Set {$n}", 'effects' => ["v{$n}" => 'b'], 'consequence' => 'c', 'attacker' => 'a', 'tradeoff' => 't', 'next' => 'hub'];
        }
        $choices[] = ['id' => 'toggle', 'label' => 'Toggle', 'consequence' => 'c', 'attacker' => 'a', 'tradeoff' => 't', 'next' => 'other'];
        $choices[] = ['id' => 'finish', 'label' => 'Finish', 'consequence' => 'c', 'attacker' => 'a', 'tradeoff' => 't', 'next' => 'end'];
        $def['scenario'] = [
            'variables' => $variables,
            'start' => 'hub',
            'nodes' => [
                ['id' => 'hub', 'event' => 'Hub.', 'choices' => $choices],
                ['id' => 'other', 'event' => 'Other.', 'choices' => [['id' => 'back', 'label' => 'Back', 'consequence' => 'c', 'attacker' => 'a', 'tradeoff' => 't', 'next' => 'hub']]],
                ['id' => 'end', 'event' => 'End.', 'ending' => true],
            ],
            'checkpoints' => [['id' => 'done', 'kind' => 'outcome', 'title' => 'Done.', 'when' => ['v1' => 'b'], 'ending' => true]],
        ];

        $errors = app(LabDefinitionValidator::class)->check($def)['errors'];
        expect($errors)->toHaveKey('scenario.nodes')->and($errors['scenario.nodes'][0])->toContain('500 reachable states');
    });

    it('does not restart attempts for a change of wording, and does for a change of behaviour', function () {
        $def = irDef();
        $words = $def;
        $words['scenario']['nodes'][0]['event'] = 'Other.';
        $words['scenario']['nodes'][0]['choices'][0]['label'] = 'Other label';
        $words['scenario']['nodes'][0]['choices'][0]['tradeoff'] = 'Other trade-off';
        $words['scenario']['variables'][0]['label'] = 'Other';
        expect(LabDefinition::breakingKey($words))->toBe(LabDefinition::breakingKey($def));

        $behaviour = $def;
        $behaviour['scenario']['nodes'][0]['choices'][0]['effects'] = [];
        expect(LabDefinition::breakingKey($behaviour))->not->toBe(LabDefinition::breakingKey($def));
    });
});
