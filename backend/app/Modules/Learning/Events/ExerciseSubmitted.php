<?php

namespace App\Modules\Learning\Events;

use App\Models\ExerciseAttempt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An exercise attempt was scored. Dispatched after the transaction commits. Phase 6 listens to
 * it to record the learning activity (ARCHITECTURE §3.2).
 */
final class ExerciseSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly ExerciseAttempt $attempt) {}
}
