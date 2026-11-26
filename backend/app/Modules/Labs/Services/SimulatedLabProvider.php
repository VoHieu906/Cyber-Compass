<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;
use App\Modules\Labs\Engines\FirewallRulesEngine;
use App\Modules\Labs\Engines\HttpInspectorEngine;
use App\Modules\Labs\Engines\LabActionRejected;
use App\Modules\Labs\Engines\LabEngine;
use App\Modules\Labs\Engines\LabLimits;
use App\Modules\Labs\Engines\Shell\LinuxShellEngine;
use App\Modules\Learning\Services\QuestionTypes;

/**
 * Runs labs on the in-memory PHP engines (D28). The attempt state is an envelope:
 * {engine: the engine's state, answers: {checkpoint id: true}}. Answer checkpoints work the
 * same for every engine: the learner submits text, compared with the accepted answers ignoring
 * case and extra spaces, and the answers never leave the server.
 */
final class SimulatedLabProvider implements LabProvider
{
    /** @var array<string, LabEngine> */
    private array $engines;

    public function __construct(LinuxShellEngine $shell, FirewallRulesEngine $firewall, HttpInspectorEngine $http)
    {
        foreach ([$shell, $firewall, $http] as $engine) {
            $this->engines[$engine->key()] = $engine;
        }
    }

    public function key(): string
    {
        return 'simulated';
    }

    /** @return list<string> */
    public function engineKeys(): array
    {
        return array_keys($this->engines);
    }

    public function engineFor(string $key): ?LabEngine
    {
        return $this->engines[$key] ?? null;
    }

    public function initialState(Lab $lab): array
    {
        return ['engine' => $this->engine($lab->engine)->initialState($lab->scenario), 'answers' => (object) []];
    }

    public function apply(Lab $lab, array $state, array $action): array
    {
        if (($action['type'] ?? null) === 'answer') {
            return $this->answer($lab, $state, $action);
        }
        $result = $this->engine($lab->engine)->apply($lab->scenario, $state['engine'], $action);
        $state['engine'] = $result['state'];

        return ['state' => $state, 'output' => $result['output']];
    }

    public function checkpoints(Lab $lab, array $state): array
    {
        $engine = $this->engine($lab->engine);
        $answers = (array) ($state['answers'] ?? []);

        return array_map(fn (array $c) => [
            'id' => $c['id'],
            'title' => $c['title'],
            'kind' => $c['kind'],
            'hint' => $c['hint'] ?? null,
            'passed' => $c['kind'] === 'answer' ? isset($answers[$c['id']]) : $engine->checkpointPassed($lab->scenario, $state['engine'], $c),
        ], $lab->scenario['checkpoints']);
    }

    public function publicScenario(Lab $lab): array
    {
        return $this->engine($lab->engine)->publicScenario($lab->scenario);
    }

    public function view(Lab $lab, array $state): array
    {
        return $this->engine($lab->engine)->view($lab->scenario, $state['engine']);
    }

    /** @return array{state: array<string, mixed>, output: array<string, mixed>} */
    private function answer(Lab $lab, array $state, array $action): array
    {
        $checkpoint = collect($lab->scenario['checkpoints'])->first(fn ($c) => $c['kind'] === 'answer' && $c['id'] === ($action['checkpoint'] ?? null));
        $value = $action['value'] ?? null;
        if (! $checkpoint) {
            throw new LabActionRejected('There is no question with that id in this lab.');
        }
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > LabLimits::MAX_ANSWER_LENGTH) {
            throw new LabActionRejected('Answer with text of at most '.LabLimits::MAX_ANSWER_LENGTH.' characters.');
        }
        $correct = in_array(QuestionTypes::normalize($value), array_map(QuestionTypes::normalize(...), $checkpoint['answers']), true);
        if ($correct) {
            $answers = (array) ($state['answers'] ?? []);
            $answers[$checkpoint['id']] = true;
            $state['answers'] = $answers;
        }

        return ['state' => $state, 'output' => ['checkpoint' => $checkpoint['id'], 'correct' => $correct]];
    }

    private function engine(string $key): LabEngine
    {
        return $this->engines[$key] ?? throw new \LogicException("No lab engine {$key}.");
    }
}
