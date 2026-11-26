<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;
use App\Modules\Labs\Engines\LabActionRejected;

/**
 * Proves a lab works (D78): every reference solution is replayed through the engine from the
 * initial state and must complete every checkpoint; a decoy (a tempting wrong path) must never
 * complete the lab. Completion counts at the first moment every checkpoint passes, which is how
 * LabSession stamps it for a learner.
 */
final class LabSolutions
{
    public const MAX_LISTED = 5;

    public const MAX_ACTIONS = 200;

    public function __construct(private readonly LabRunner $runner) {}

    /**
     * @param  Lab  $lab  may be unsaved, see LabDefinition::lab()
     * @return array{errors: array<string, list<string>>, warnings: array<string, list<string>>}
     */
    public function replay(Lab $lab): array
    {
        $errors = [];
        $warnings = [];
        $solutions = $lab->solutions ?? LabDefinition::noSolutions();

        if ($solutions['solutions'] === []) {
            $errors['solutions.solutions'][] = 'Add at least one reference solution: the actions of a learner who completes the lab.';
        }
        foreach (['solutions', 'decoys'] as $kind) {
            foreach ($solutions[$kind] as $i => $item) {
                $run = $this->run($lab, $item['actions']);
                $at = "solutions.{$kind}.{$i}";
                if ($run['refused'] !== null) {
                    $errors[$at][] = "Action {$run['refused']['step']} was refused: {$run['refused']['message']}";
                } elseif ($kind === 'solutions' && ! $run['completed']) {
                    $errors[$at][] = 'This solution does not complete the lab. Not passed at the end: '.implode(', ', $run['missing']).'.';
                } elseif ($kind === 'decoys' && $run['completed']) {
                    $errors[$at][] = 'This decoy completes the lab, so it is not a wrong path. Change the decoy or the checkpoints.';
                }
            }
        }

        $start = $this->runner->progress($lab, $this->runner->start($lab))['passed'];
        if ($start !== []) {
            $warnings['scenario.checkpoints'][] = 'Already passed before the learner does anything: '.implode(', ', $start).'.';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     * @return array{completed: bool, missing: list<string>, refused: ?array{step: int, message: string}}
     */
    private function run(Lab $lab, array $actions): array
    {
        $ids = array_column($lab->scenario['checkpoints'], 'id');
        $state = $this->runner->start($lab);
        $completed = false;
        $passed = [];
        foreach ($actions as $n => $action) {
            try {
                $step = $this->runner->step($lab, $state, $action);
            } catch (LabActionRejected $e) {
                return ['completed' => $completed, 'missing' => [], 'refused' => ['step' => $n + 1, 'message' => $e->getMessage()]];
            }
            $state = $step['state'];
            $passed = $step['passed'];
            $completed = $completed || $step['complete'];
        }

        return ['completed' => $completed, 'missing' => array_values(array_diff($ids, $passed)), 'refused' => null];
    }
}
