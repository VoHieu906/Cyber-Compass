<?php

namespace App\Modules\Labs\Engines;

/**
 * Respond to an incident by technical actions and see their effect on technical state (D80,
 * ARCHITECTURE §7). The scenario is a data-driven state machine: `variables` (each with a few named
 * values and a starting one), `nodes` with `choices` (an action, the effects it has on variables,
 * the attacker's next move, a trade-off the author explains in the debrief, the node it leads to),
 * and outcome checkpoints on the variables, so different orders can pass. A choice may list
 * `requires`: alternatives (any of them), each a set of variable values that must all hold.
 *
 * The validator walks every reachable state (IncidentGraph, at most 500) and refuses scenarios
 * with a node that cannot be reached, a state with nothing to choose, a state from which no ending
 * can be reached, or a task no sequence of choices can pass. During play the engine only looks the
 * state up in that table: when a learner's choices leave a task out of reach, the view says so
 * plainly, never which choice would have saved it, and points to starting over.
 *
 * State: {node, vars, history [{node, choice, changes [{var, from, to}]}]}.
 */
final class IncidentResponseEngine implements LabEngine
{
    /** Most choices one run may take: a scenario with a loop cannot grow the state forever. */
    private const MAX_HISTORY = 60;

    public function key(): string
    {
        return 'incident-response';
    }

    public function checkpointKinds(): array
    {
        return ['outcome'];
    }

    public function validateScenario(array $scenario): array
    {
        $errors = [];
        $values = [];
        $varIds = [];
        foreach ($scenario['variables'] as $i => $var) {
            if (isset($varIds[$var['id']])) {
                $errors["variables.{$i}.id"][] = 'duplicate variable id';
            }
            $varIds[$var['id']] = true;
            $ids = array_column($var['values'], 'id');
            if (count($ids) !== count(array_unique($ids))) {
                $errors["variables.{$i}.values"][] = 'two values have the same id';
            }
            if (! in_array($var['initial'], $ids, true)) {
                $errors["variables.{$i}.initial"][] = 'must be one of the values';
            }
            $values[$var['id']] = $ids;
        }
        $check = function (array $map, string $at) use (&$errors, $values) {
            foreach ($map as $var => $value) {
                if (! isset($values[$var])) {
                    $errors[$at][] = "no variable {$var}";
                } elseif (! in_array($value, $values[$var], true)) {
                    $errors[$at][] = "{$value} is not a value of {$var}";
                }
            }
        };

        $nodeIds = array_column($scenario['nodes'], 'id');
        if (count($nodeIds) !== count(array_unique($nodeIds))) {
            $errors['nodes'][] = 'two nodes have the same id';
        }
        if (! in_array($scenario['start'], $nodeIds, true)) {
            $errors['start'][] = 'must be one of the node ids';
        }
        foreach ($scenario['nodes'] as $i => $node) {
            $ending = $node['ending'] ?? false;
            $choices = $node['choices'] ?? [];
            if ($ending && $choices !== []) {
                $errors["nodes.{$i}.choices"][] = 'an ending has no choices';
            }
            if (! $ending && $choices === []) {
                $errors["nodes.{$i}.choices"][] = 'a node that is not an ending needs at least one choice, or the incident has no way on';
            }
            $seen = [];
            foreach ($choices as $j => $choice) {
                if (isset($seen[$choice['id']])) {
                    $errors["nodes.{$i}.choices.{$j}.id"][] = 'another choice of this node has this id';
                }
                $seen[$choice['id']] = true;
                if (! in_array($choice['next'], $nodeIds, true)) {
                    $errors["nodes.{$i}.choices.{$j}.next"][] = 'must be one of the node ids';
                }
                $check($choice['effects'] ?? [], "nodes.{$i}.choices.{$j}.effects");
                foreach ($choice['requires'] ?? [] as $k => $all) {
                    $check($all, "nodes.{$i}.choices.{$j}.requires.{$k}");
                }
            }
        }
        foreach ($scenario['checkpoints'] as $i => $checkpoint) {
            if ($checkpoint['kind'] === 'outcome') {
                $check($checkpoint['when'], "checkpoints.{$i}.when");
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        return $this->graphErrors($scenario);
    }

    public function initialState(array $scenario): array
    {
        return ['node' => $scenario['start'], 'vars' => IncidentGraph::initialVars($scenario), 'history' => []];
    }

    public function apply(array $scenario, array $state, array $action): array
    {
        if (($action['type'] ?? null) !== 'choose') {
            throw new LabActionRejected('Unknown action. Use choose.');
        }
        $node = $this->node($scenario, $state['node']);
        $choice = collect($node['choices'] ?? [])->first(fn ($c) => $c['id'] === ($action['choice'] ?? null));
        if (($node['ending'] ?? false) || $choice === null || ! IncidentGraph::available($choice, $state['vars'])) {
            throw new LabActionRejected('That choice is not available now.');
        }
        if (count($state['history']) >= self::MAX_HISTORY) {
            throw new LabActionRejected('This incident has gone on for too long. Start over.');
        }
        $changes = [];
        foreach ($choice['effects'] ?? [] as $var => $value) {
            if ($state['vars'][$var] !== $value) {
                $changes[] = ['var' => $var, 'from' => $state['vars'][$var], 'to' => $value];
            }
        }
        $state['history'][] = ['node' => $state['node'], 'choice' => $choice['id'], 'changes' => $changes];
        $state['vars'] = array_merge($state['vars'], $choice['effects'] ?? []);
        $state['node'] = $choice['next'];

        return ['state' => $state, 'output' => ['consequence' => $choice['consequence'], 'attacker' => $choice['attacker'], 'changes' => $this->changes($scenario, $changes)]];
    }

    public function checkpointPassed(array $scenario, array $state, array $checkpoint): bool
    {
        return IncidentGraph::holds($checkpoint, (bool) ($this->node($scenario, $state['node'])['ending'] ?? false), $state['vars']);
    }

    /** The current facts, `variable:value`, for a conclusion to cite. */
    public function citableIds(array $scenario): array
    {
        $ids = [];
        foreach ($scenario['variables'] as $var) {
            foreach ($var['values'] as $value) {
                $ids[] = "{$var['id']}:{$value['id']}";
            }
        }

        return $ids;
    }

    public function citable(array $scenario, array $state): array
    {
        $out = [];
        foreach ($scenario['variables'] as $var) {
            $value = collect($var['values'])->firstWhere('id', $state['vars'][$var['id']]);
            $out[] = ['id' => "{$var['id']}:{$value['id']}", 'label' => "{$var['label']}: {$value['label']}"];
        }

        return $out;
    }

    /** Only the variables: where the incident goes next stays in the nodes until the learner gets there. */
    public function publicScenario(array $scenario): array
    {
        return ['variables' => array_map(fn (array $v) => ['id' => $v['id'], 'label' => $v['label'], 'values' => $v['values']], $scenario['variables'])];
    }

    public function view(array $scenario, array $state): array
    {
        $node = $this->node($scenario, $state['node']);
        $ending = (bool) ($node['ending'] ?? false);
        $labels = fn (string $var, string $value) => collect(collect($scenario['variables'])->firstWhere('id', $var)['values'])->firstWhere('id', $value)['label'];
        $timeline = [];
        $last = null;
        foreach ($state['history'] as $entry) {
            $choice = $this->choice($scenario, $entry['node'], $entry['choice']);
            $timeline[] = ['decision' => $choice['label'], 'consequence' => $choice['consequence'], 'attacker' => $choice['attacker'], 'changes' => $this->changes($scenario, $entry['changes'])];
            $last = $choice;
        }
        $table = IncidentGraph::of($scenario);
        $row = $table['truncated'] ? null : ($table['states'][IncidentGraph::key($state['node'], $state['vars'])] ?? null);

        return [
            // What is happening now: the alert at the start, the attacker's last move after that, and the closing line at an ending.
            'situation' => $ending || $last === null ? $node['event'] : $last['attacker'],
            'ended' => $ending,
            'vars' => array_map(fn (array $v) => ['id' => $v['id'], 'label' => $v['label'], 'value' => $state['vars'][$v['id']], 'value_label' => $labels($v['id'], $state['vars'][$v['id']])], $scenario['variables']),
            'choices' => $ending ? [] : array_map(fn (array $c) => ['id' => $c['id'], 'label' => $c['label'], 'available' => IncidentGraph::available($c, $state['vars'])], $node['choices']),
            'timeline' => $timeline,
            // Read from the precomputed table: tasks no sequence of choices can pass from here, only when the lab can no longer be completed.
            'dead_end' => $row !== null && ! $row['complete'] ? ['unreachable' => array_values(array_diff(array_keys(IncidentGraph::outcomes($scenario)), $row['reach']))] : null,
        ];
    }

    /** @return array{path: list<array<string, mixed>>, final: list<array<string, string>>} */
    public function debrief(array $scenario, array $state): ?array
    {
        $table = IncidentGraph::of($scenario);
        $vars = IncidentGraph::initialVars($scenario);
        $path = [];
        foreach ($state['history'] as $entry) {
            $node = $this->node($scenario, $entry['node']);
            $alternatives = [];
            foreach ($node['choices'] as $choice) {
                if ($choice['id'] === $entry['choice'] || ! IncidentGraph::available($choice, $vars)) {
                    continue;
                }
                $after = IncidentGraph::key($choice['next'], array_merge($vars, $choice['effects'] ?? []));
                $alternatives[] = ['label' => $choice['label'], 'tradeoff' => $choice['tradeoff'], 'keeps_open' => (bool) ($table['states'][$after]['complete'] ?? false)];
            }
            $chosen = $this->choice($scenario, $entry['node'], $entry['choice']);
            $path[] = ['decision' => $chosen['label'], 'tradeoff' => $chosen['tradeoff'], 'alternatives' => $alternatives];
            $vars = array_merge($vars, $chosen['effects'] ?? []);
        }

        return [
            'path' => $path,
            'final' => array_map(fn (array $v) => ['label' => $v['label'], 'value' => collect($v['values'])->firstWhere('id', $state['vars'][$v['id']])['label']], $scenario['variables']),
        ];
    }

    /**
     * What a walk of every reachable state finds wrong (D80): too many states, a node nothing
     * reaches, a state with no way on, a state that cannot reach an ending, a task nothing passes.
     *
     * @return array<string, list<string>>
     */
    private function graphErrors(array $scenario): array
    {
        $table = IncidentGraph::of($scenario);
        if ($table['truncated']) {
            return ['nodes' => ['The incident has more than '.IncidentGraph::MAX_STATES.' reachable states (a node and the values of the variables). Use fewer variables or values, or fewer ways to change them.']];
        }
        $errors = [];
        $reached = [];
        foreach ($table['states'] as $state) {
            $reached[$state['node']] = true;
            $node = $this->node($scenario, $state['node']);
            if (! ($node['ending'] ?? false) && $state['succ'] === []) {
                $errors['nodes.'.$this->index($scenario, $state['node']).'.choices'][] = 'In some states every choice of this node is unavailable, so the incident cannot go on. Add a choice that is always available there.';
            }
            if (! $state['ends']) {
                $errors['nodes.'.$this->index($scenario, $state['node'])][] = 'From some states of this node no choices lead to an ending: a cycle that never ends.';
            }
        }
        foreach ($scenario['nodes'] as $i => $node) {
            if (! isset($reached[$node['id']])) {
                $errors["nodes.{$i}"][] = 'No sequence of choices reaches this node.';
            }
        }
        $start = $table['states'][$table['start']];
        foreach ($scenario['checkpoints'] as $i => $checkpoint) {
            if ($checkpoint['kind'] === 'outcome' && ! in_array($checkpoint['id'], $start['reach'], true)) {
                $errors["checkpoints.{$i}"][] = 'No sequence of choices makes this task true, so nobody could finish the lab.';
            }
        }
        if (! $start['complete'] && ! isset($errors['checkpoints']) && collect($errors)->keys()->filter(fn ($k) => str_starts_with($k, 'checkpoints.'))->isEmpty()) {
            $errors['checkpoints'][] = 'Each task can be passed, but no sequence of choices passes them all together.';
        }

        return $errors;
    }

    /**
     * @param  list<array{var: string, from: string, to: string}>  $changes
     * @return list<array{label: string, from: string, to: string}>
     */
    private function changes(array $scenario, array $changes): array
    {
        $out = [];
        foreach ($changes as $c) {
            $var = collect($scenario['variables'])->firstWhere('id', $c['var']);
            $out[] = ['label' => $var['label'], 'from' => collect($var['values'])->firstWhere('id', $c['from'])['label'], 'to' => collect($var['values'])->firstWhere('id', $c['to'])['label']];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function node(array $scenario, string $id): array
    {
        return collect($scenario['nodes'])->firstWhere('id', $id);
    }

    /** @return array<string, mixed> */
    private function choice(array $scenario, string $node, string $choice): array
    {
        return collect($this->node($scenario, $node)['choices'])->firstWhere('id', $choice);
    }

    private function index(array $scenario, string $node): int
    {
        return (int) array_search($node, array_column($scenario['nodes'], 'id'), true);
    }
}
