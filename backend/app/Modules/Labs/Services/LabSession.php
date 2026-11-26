<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\User;
use App\Modules\Labs\Engines\LabActionRejected;
use App\Modules\Labs\Events\LabCompleted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Starting a lab, applying actions and resetting (ARCHITECTURE §7.1). Every change locks the
 * attempt row and checks the version the browser last saw, so two tabs cannot overwrite each
 * other (409). The stored state is capped; completing every checkpoint stamps `completed_at`
 * once and fires LabCompleted after commit. The engine step itself is LabRunner's (D79).
 *
 * An attempt is pinned to the lab's `scenario_version` (D75): when a publish changed what its
 * state was built from, sync() starts it over, keeps `completed_at`, and leaves a notice.
 */
final class LabSession
{
    public function __construct(private readonly LabRunner $runner) {}

    /** @return array{0: LabAttempt, 1: bool} the attempt, and whether it was created now */
    public function start(User $user, Lab $lab): array
    {
        $existing = LabAttempt::query()->where('user_id', $user->id)->where('lab_id', $lab->id)->first();
        if ($existing) {
            return [$this->sync($existing, $lab), false];
        }
        try {
            return [LabAttempt::query()->create([
                'user_id' => $user->id,
                'lab_id' => $lab->id,
                'state' => $this->runner->start($lab),
                'scenario_version' => $lab->scenario_version,
                'checkpoints_passed' => [],
            ]), true];
        } catch (UniqueConstraintViolationException) {
            // Two tabs started at once; the other one won.
            return [LabAttempt::query()->where('user_id', $user->id)->where('lab_id', $lab->id)->firstOrFail(), false];
        }
    }

    /** Starts an attempt over when the lab's scenario changed since it began (D75). Completion stays. */
    public function sync(LabAttempt $attempt, Lab $lab): LabAttempt
    {
        if ($attempt->scenario_version === $lab->scenario_version) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt, $lab) {
            $locked = LabAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->scenario_version !== $lab->scenario_version) {
                $locked->update([
                    'state' => $this->runner->start($lab),
                    'state_version' => $locked->state_version + 1,
                    'scenario_version' => $lab->scenario_version,
                    'checkpoints_passed' => [],
                    'restarted_at' => now(),
                ]);
            }

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array{attempt: LabAttempt, output: array<string, mixed>, newly_passed: list<string>}
     */
    public function act(LabAttempt $attempt, int $version, array $action): array
    {
        return DB::transaction(function () use ($attempt, $version, $action) {
            $attempt = $this->lockAt($attempt, $version);
            $lab = $attempt->lab;

            try {
                $step = $this->runner->step($lab, $attempt->state, $action);
            } catch (LabActionRejected $e) {
                throw ValidationException::withMessages(['action' => [$e->getMessage()]]);
            }

            $before = $attempt->checkpoints_passed;
            $attempt->fill([
                'state' => $step['state'],
                'state_version' => $attempt->state_version + 1,
                'action_count' => $attempt->action_count + 1,
                'checkpoints_passed' => $step['passed'],
                'restarted_at' => null,
            ]);
            $first = $step['complete'] && $attempt->completed_at === null;
            if ($first) {
                $attempt->completed_at = now();
            }
            $attempt->save();
            if ($first) {
                LabCompleted::dispatch($attempt);
            }

            return ['attempt' => $attempt, 'output' => $step['output'], 'newly_passed' => array_values(array_diff($step['passed'], $before))];
        });
    }

    /** Starts the lab over. Completion stays: it records that the learner finished it once. */
    public function reset(LabAttempt $attempt, int $version): LabAttempt
    {
        return DB::transaction(function () use ($attempt, $version) {
            $attempt = $this->lockAt($attempt, $version);
            $attempt->update([
                'state' => $this->runner->start($attempt->lab),
                'state_version' => $attempt->state_version + 1,
                'checkpoints_passed' => [],
                'restarted_at' => null,
            ]);

            return $attempt;
        });
    }

    private function lockAt(LabAttempt $attempt, int $version): LabAttempt
    {
        $locked = LabAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
        if ($locked->state_version !== $version) {
            throw new ConflictHttpException(__('This lab changed in another tab or window. Reload to continue from there.'));
        }

        return $locked->setRelation('lab', $attempt->lab);
    }
}
