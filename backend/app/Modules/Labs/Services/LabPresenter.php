<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;
use App\Models\LabAttempt;

/** The public shapes of labs and attempts. Never the scenario's answers or hidden test data. */
final class LabPresenter
{
    public function __construct(private readonly LabProviders $providers) {}

    /** @return array<string, mixed> */
    public function summary(Lab $lab, ?LabAttempt $attempt): array
    {
        $total = count($lab->scenario['checkpoints']);

        return [
            'slug' => $lab->slug,
            'title' => $lab->title,
            'summary' => $lab->summary,
            'engine' => $lab->engine,
            'difficulty' => $lab->difficulty,
            'estimated_minutes' => $lab->estimated_minutes,
            'domain' => ['slug' => $lab->domain->slug, 'name' => $lab->domain->name],
            // Only a lesson learners can see is linked.
            'lesson' => $lab->lesson ? ['slug' => $lab->lesson->slug, 'title' => $lab->lesson->title] : null,
            'progress' => [
                'started' => $attempt !== null,
                'completed' => $attempt?->completed_at !== null,
                'passed' => $attempt ? count($attempt->checkpoints_passed) : 0,
                'total' => $total,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Lab $lab, ?LabAttempt $attempt): array
    {
        $provider = $this->providers->for($lab);

        return $this->summary($lab, $attempt) + [
            'briefing' => $lab->briefing,
            'scenario' => (object) $provider->publicScenario($lab),
            'checkpoints' => $attempt
                ? $provider->checkpoints($lab, $attempt->state)
                : array_map(fn ($c) => ['id' => $c['id'], 'title' => $c['title'], 'kind' => $c['kind'], 'hint' => $c['hint'] ?? null, 'passed' => false], $lab->scenario['checkpoints']),
            'attempt' => $attempt ? $this->attempt($lab, $attempt) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function attempt(Lab $lab, LabAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'version' => $attempt->state_version,
            'state' => (object) $this->providers->for($lab)->view($lab, $attempt->state),
            'completed_at' => $attempt->completed_at?->toIso8601String(),
            // The scenario changed since the learner began, so the lab started over (D75).
            'restarted' => $attempt->restarted_at !== null,
        ];
    }
}
