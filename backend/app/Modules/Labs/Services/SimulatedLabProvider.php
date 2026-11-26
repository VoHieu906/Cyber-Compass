<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;
use App\Modules\Labs\Engines\EvidenceReviewEngine;
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
    /** The most items one conclusion may cite when the lab sets no `max_cited`. */
    public const MAX_CITED = 6;

    /** @var array<string, LabEngine> */
    private array $engines;

    public function __construct(LinuxShellEngine $shell, FirewallRulesEngine $firewall, HttpInspectorEngine $http, EvidenceReviewEngine $evidence)
    {
        foreach ([$shell, $firewall, $http, $evidence] as $engine) {
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
        if (($action['type'] ?? null) === 'conclude') {
            return $this->conclude($lab, $state, $action);
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
            'passed' => in_array($c['kind'], ['answer', 'conclusion'], true) ? isset($answers[$c['id']]) : $engine->checkpointPassed($lab->scenario, $state['engine'], $c),
        ] + ($c['kind'] === 'conclusion' ? [
            // Never `answer`, `evidence` or `explanation`. The order of the options is the presenter's (it must not tell the answer).
            'question' => $c['question'],
            'options' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']], $c['options']),
            'max_cited' => $c['max_cited'] ?? self::MAX_CITED,
            'citable' => $engine->citable($lab->scenario, $state['engine']),
        ] : []), $lab->scenario['checkpoints']);
    }

    public function publicScenario(Lab $lab): array
    {
        return $this->engine($lab->engine)->publicScenario($lab->scenario);
    }

    public function view(Lab $lab, array $state): array
    {
        return $this->engine($lab->engine)->view($lab->scenario, $state['engine']);
    }

    /**
     * What a learner may read once the lab is complete: the engine's reasoning and the conclusions'
     * explanations. Null when there is none. The caller checks completion (D80).
     *
     * @return ?array<string, mixed>
     */
    public function debrief(Lab $lab, array $state): ?array
    {
        $own = $this->engine($lab->engine)->debrief($lab->scenario, $state['engine']);
        $conclusions = [];
        foreach ($lab->scenario['checkpoints'] as $c) {
            if ($c['kind'] === 'conclusion' && isset($c['explanation'])) {
                $conclusions[] = ['checkpoint' => $c['id'], 'title' => $c['title'], 'explanation' => $c['explanation']];
            }
        }

        return $own === null && $conclusions === [] ? null : ($own ?? []) + ['conclusions' => $conclusions];
    }

    /**
     * A verdict with the evidence that backs it (D80). Right when the option is the answer, at
     * least `min_cited` of the cited items are among the checkpoint's evidence, no more than
     * `max_cited` are cited, and every cited item is one the learner can cite now. The reply says
     * only right or not yet, never which half was wrong.
     *
     * @return array{state: array<string, mixed>, output: array<string, mixed>}
     */
    private function conclude(Lab $lab, array $state, array $action): array
    {
        $checkpoint = collect($lab->scenario['checkpoints'])->first(fn ($c) => $c['kind'] === 'conclusion' && $c['id'] === ($action['checkpoint'] ?? null));
        $option = $action['option'] ?? null;
        $cited = $action['cited'] ?? null;
        if (! $checkpoint) {
            throw new LabActionRejected('There is no conclusion with that id in this lab.');
        }
        if (! is_string($option) || ! in_array($option, array_column($checkpoint['options'], 'id'), true)
            || ! is_array($cited) || ! array_is_list($cited) || count($cited) > self::MAX_CITED || array_filter($cited, fn ($c) => ! is_string($c)) !== []) {
            throw new LabActionRejected('Choose one of the options and cite at most '.self::MAX_CITED.' items.');
        }
        $cited = array_values(array_unique($cited));
        $available = array_column($this->engine($lab->engine)->citable($lab->scenario, $state['engine']), 'id');
        $correct = $option === $checkpoint['answer']
            && array_diff($cited, $available) === []
            && count($cited) <= ($checkpoint['max_cited'] ?? self::MAX_CITED)
            && count(array_intersect($cited, $checkpoint['evidence'])) >= ($checkpoint['min_cited'] ?? 1);
        if ($correct) {
            $answers = (array) ($state['answers'] ?? []);
            $answers[$checkpoint['id']] = true;
            $state['answers'] = $answers;
        }

        return ['state' => $state, 'output' => ['checkpoint' => $checkpoint['id'], 'correct' => $correct]];
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
