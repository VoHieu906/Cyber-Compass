<?php

namespace App\Modules\Labs\Engines;

/**
 * A lab simulation that runs on the server (ARCHITECTURE §7, D28). Engines are pure in-memory
 * functions of (scenario, state, action): they never run processes or touch the real file
 * system (§7.1, enforced by tests/Feature/Labs/EngineSafetyTest.php).
 *
 * The scenario may hold answers; `publicScenario()` is the only part the browser sees.
 * Checkpoints of kind `answer` are handled by LabSession for every engine; an engine checks
 * the other kinds it declares.
 */
interface LabEngine
{
    public function key(): string;

    /**
     * Problems with a scenario from the content file, as messages; empty when valid.
     *
     * @param  array<string, mixed>  $scenario
     * @return list<string>
     */
    public function validateScenario(array $scenario): array;

    /** @return list<string> checkpoint kinds this engine evaluates, besides `answer` */
    public function checkpointKinds(): array;

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function initialState(array $scenario): array;

    /**
     * Applies one action. A refused command that a real tool would answer with an error message
     * (an unknown command, unsupported syntax) is output, not an exception; a malformed action
     * throws LabActionRejected (422).
     *
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $action
     * @return array{state: array<string, mixed>, output: array<string, mixed>}
     *
     * @throws LabActionRejected
     */
    public function apply(array $scenario, array $state, array $action): array;

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $checkpoint  of one of checkpointKinds()
     */
    public function checkpointPassed(array $scenario, array $state, array $checkpoint): bool;

    /**
     * What the learner may see of the scenario: never answers or hidden test data.
     *
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function publicScenario(array $scenario): array;

    /**
     * The learner's view of the state.
     *
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function view(array $scenario, array $state): array;
}
