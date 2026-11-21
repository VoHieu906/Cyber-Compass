<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\User;
use App\Modules\Labs\Engines\LabActionRejected;
use App\Modules\Labs\Engines\LabLimits;
use App\Modules\Labs\Events\LabCompleted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Starting a lab, applying actions and resetting (ARCHITECTURE §7.1). Every change locks the
 * attempt row and checks the version the browser last saw, so two tabs cannot overwrite each
 * other (409). The stored state is capped; completing every checkpoint stamps `completed_at`
 * once and fires LabCompleted after commit.
 */
final class LabSession
{
    public function __construct(private readonly LabProviders $providers) {}

    /** @return array{0: LabAttempt, 1: bool} the attempt, and whether it was created now */
    public function start(User $user, Lab $lab): array
    {
        $existing = LabAttempt::query()->where('user_id', $user->id)->where('lab_id', $lab->id)->first();
        if ($existing) {
            return [$existing, false];
        }
        try {
            $state = $this->providers->for($lab)->initialState($lab);

            return [LabAttempt::query()->create([
                'user_id' => $user->id,
                'lab_id' => $lab->id,
                'state' => $state,
                'checkpoints_passed' => [],
            ]), true];
        } catch (UniqueConstraintViolationException) {
            // Two tabs started at once; the other one won.
            return [LabAttempt::query()->where('user_id', $user->id)->where('lab_id', $lab->id)->firstOrFail(), false];
        }
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
            $provider = $this->providers->for($lab);

            try {
                $result = $provider->apply($lab, $attempt->state, $action);
            } catch (LabActionRejected $e) {
                throw ValidationException::withMessages(['action' => [$e->getMessage()]]);
            }
            if (strlen((string) json_encode($result['state'])) > LabLimits::MAX_STATE_BYTES) {
                throw ValidationException::withMessages(['action' => [__('This lab has stored as much as it can. Reset it to start over.')]]);
            }

            $before = $attempt->checkpoints_passed;
            $passed = array_values(array_map(fn ($c) => $c['id'], array_filter($provider->checkpoints($lab, $result['state']), fn ($c) => $c['passed'])));
            $complete = count($passed) === count($lab->scenario['checkpoints']);

            $attempt->fill([
                'state' => $result['state'],
                'state_version' => $attempt->state_version + 1,
                'action_count' => $attempt->action_count + 1,
                'checkpoints_passed' => $passed,
            ]);
            $first = $complete && $attempt->completed_at === null;
            if ($first) {
                $attempt->completed_at = now();
            }
            $attempt->save();
            if ($first) {
                LabCompleted::dispatch($attempt);
            }

            return ['attempt' => $attempt, 'output' => $result['output'], 'newly_passed' => array_values(array_diff($passed, $before))];
        });
    }

    /** Starts the lab over. Completion stays: it records that the learner finished it once. */
    public function reset(LabAttempt $attempt, int $version): LabAttempt
    {
        return DB::transaction(function () use ($attempt, $version) {
            $attempt = $this->lockAt($attempt, $version);
            $attempt->update([
                'state' => $this->providers->for($attempt->lab)->initialState($attempt->lab),
                'state_version' => $attempt->state_version + 1,
                'checkpoints_passed' => [],
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
