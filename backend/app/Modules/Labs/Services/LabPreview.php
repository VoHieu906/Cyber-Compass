<?php

namespace App\Modules\Labs\Services;

use App\Models\Domain;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\User;
use App\Modules\Labs\Engines\LabActionRejected;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * An admin plays a lab's working copy, draft included (D79). The state lives in the cache for
 * two hours, per admin and lab; nothing is written to the database and no event fires, so
 * there is no attempt, activity, streak, notification or LabCompleted. It runs the same
 * LabRunner as a learner. A changed draft starts the preview over, because the state was built
 * from the earlier scenario.
 */
final class LabPreview
{
    private const TTL_SECONDS = 7200;

    public function __construct(
        private readonly LabRunner $runner,
        private readonly LabWorkingCopy $copies,
        private readonly LabPresenter $presenter,
    ) {}

    /** @return array<string, mixed> what GET /labs/{slug} returns, for the working copy */
    public function open(User $admin, Lab $lab, bool $fresh = false): array
    {
        [$transient, $def] = $this->transient($lab);
        $entry = $this->entry($admin, $lab, $transient, $def, $fresh);

        return $this->presenter->detail($transient, $this->attempt($transient, $entry));
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed> what POST /lab-attempts/{id}/actions returns
     */
    public function act(User $admin, Lab $lab, int $version, array $action): array
    {
        [$transient, $def] = $this->transient($lab);
        $entry = $this->entry($admin, $lab, $transient, $def, false);
        if ($entry['restarted'] || $entry['version'] !== $version) {
            throw new ConflictHttpException(__('This preview changed in another tab, or the draft changed. Reload to continue.'));
        }
        try {
            $step = $this->runner->step($transient, $entry['state'], $action);
        } catch (LabActionRejected $e) {
            throw ValidationException::withMessages(['action' => [$e->getMessage()]]);
        }
        $newly = array_values(array_diff($step['passed'], $entry['passed']));
        $entry = ['hash' => $entry['hash'], 'state' => $step['state'], 'version' => $entry['version'] + 1, 'passed' => $step['passed'], 'complete' => $entry['complete'] || $step['complete'], 'restarted' => false];
        $this->store($admin, $lab, $entry);
        $attempt = $this->attempt($transient, $entry);

        return [
            'output' => (object) $step['output'],
            'newly_passed' => $newly,
            'checkpoints' => $this->presenter->detail($transient, $attempt)['checkpoints'],
            'attempt' => $this->presenter->attempt($transient, $attempt),
        ];
    }

    /** @return array<string, mixed> */
    public function reset(User $admin, Lab $lab): array
    {
        return $this->open($admin, $lab, fresh: true);
    }

    /** @return array{0: Lab, 1: array<string, mixed>} */
    private function transient(Lab $lab): array
    {
        $def = $this->copies->current($lab);
        $transient = LabDefinition::lab($def, $lab->slug);
        $transient->setRelation('domain', Domain::query()->where('slug', $def['domain'])->first(['id', 'slug', 'name']));
        $transient->setRelation('lesson', null);

        return [$transient, $def];
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array{hash: string, state: array<string, mixed>, version: int, passed: list<string>, complete: bool, restarted: bool}
     */
    private function entry(User $admin, Lab $lab, Lab $transient, array $def, bool $fresh): array
    {
        $hash = LabDefinition::fingerprint($def);
        $stored = Cache::get($this->key($admin, $lab));
        if (! $fresh && is_array($stored) && $stored['hash'] === $hash) {
            return $stored + ['restarted' => false];
        }
        $state = $this->runner->start($transient);
        $entry = ['hash' => $hash, 'state' => $state, 'version' => ($stored['version'] ?? 0) + 1, 'passed' => $this->runner->progress($transient, $state)['passed'], 'complete' => false, 'restarted' => ! $fresh && is_array($stored)];
        $this->store($admin, $lab, $entry);

        return $entry;
    }

    /** @param  array<string, mixed>  $entry */
    private function store(User $admin, Lab $lab, array $entry): void
    {
        Cache::put($this->key($admin, $lab), array_diff_key($entry, ['restarted' => 1]), self::TTL_SECONDS);
    }

    /** @param  array<string, mixed>  $entry */
    private function attempt(Lab $transient, array $entry): LabAttempt
    {
        $attempt = new LabAttempt([
            'state' => $entry['state'],
            'state_version' => $entry['version'],
            'checkpoints_passed' => $entry['passed'],
            'completed_at' => $entry['complete'] ? now() : null,
            'restarted_at' => $entry['restarted'] ? now() : null,
        ]);
        $attempt->forceFill(['id' => 0]);

        return $attempt;
    }

    private function key(User $admin, Lab $lab): string
    {
        return "lab-preview:{$admin->id}:{$lab->id}";
    }
}
