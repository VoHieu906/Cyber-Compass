<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;

/**
 * Where a lab runs (ARCHITECTURE §7, D28). `simulated` runs a PHP engine in memory today; a
 * future `container` provider (an isolated task with no egress) takes the same calls, so the
 * API, the lab and the attempt schema do not change.
 */
interface LabProvider
{
    public function key(): string;

    /** @return array<string, mixed> */
    public function initialState(Lab $lab): array;

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $action
     * @return array{state: array<string, mixed>, output: array<string, mixed>}
     */
    public function apply(Lab $lab, array $state, array $action): array;

    /**
     * Every checkpoint with whether it is passed; never the answers.
     *
     * @param  array<string, mixed>  $state
     * @return list<array{id: string, title: string, kind: string, hint: ?string, passed: bool}>
     */
    public function checkpoints(Lab $lab, array $state): array;

    /** @return array<string, mixed> */
    public function publicScenario(Lab $lab): array;

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function view(Lab $lab, array $state): array;

    /**
     * Problems with a scenario before it is stored.
     *
     * @param  array<string, mixed>  $scenario
     * @return list<string>
     */
    public function validate(string $engine, array $scenario): array;
}
