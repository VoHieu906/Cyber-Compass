<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;
use App\Modules\Labs\Engines\LabActionRejected;
use App\Modules\Labs\Engines\LabLimits;

/**
 * One step of a lab, with no persistence and no events (D79). Learner attempts (LabSession),
 * the admin preview (LabPreview) and the solution replay (LabSolutions) all run through this,
 * so none of them can behave differently from what a learner gets.
 */
final class LabRunner
{
    public function __construct(private readonly LabProviders $providers) {}

    /** @return array<string, mixed> */
    public function start(Lab $lab): array
    {
        return $this->providers->for($lab)->initialState($lab);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $action
     * @return array{state: array<string, mixed>, output: array<string, mixed>, passed: list<string>, complete: bool}
     *
     * @throws LabActionRejected a malformed action, or a state that grew past its cap
     */
    public function step(Lab $lab, array $state, array $action): array
    {
        $provider = $this->providers->for($lab);
        $result = $provider->apply($lab, $state, $action);
        if (strlen((string) json_encode($result['state'])) > LabLimits::MAX_STATE_BYTES) {
            throw new LabActionRejected(__('This lab has stored as much as it can. Reset it to start over.'));
        }

        return $result + $this->progress($lab, $result['state']);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{passed: list<string>, complete: bool}
     */
    public function progress(Lab $lab, array $state): array
    {
        $passed = array_values(array_map(fn ($c) => $c['id'], array_filter($this->providers->for($lab)->checkpoints($lab, $state), fn ($c) => $c['passed'])));

        return ['passed' => $passed, 'complete' => count($passed) === count($lab->scenario['checkpoints'])];
    }
}
