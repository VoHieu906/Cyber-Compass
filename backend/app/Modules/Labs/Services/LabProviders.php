<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;

/** Resolves a lab's provider by its `provider` key (only `simulated` exists today, D28). */
final class LabProviders
{
    public function __construct(private readonly SimulatedLabProvider $simulated) {}

    public function for(Lab $lab): LabProvider
    {
        return $this->byKey($lab->provider) ?? throw new \LogicException("No lab provider {$lab->provider}.");
    }

    public function byKey(string $key): ?LabProvider
    {
        return $key === $this->simulated->key() ? $this->simulated : null;
    }
}
