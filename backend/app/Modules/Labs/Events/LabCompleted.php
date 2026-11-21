<?php

namespace App\Modules\Labs\Events;

use App\Models\LabAttempt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A learner passed every checkpoint of a lab for the first time. Dispatched after commit;
 * Phase 6 listens to record the activity (ARCHITECTURE §3.2).
 */
final class LabCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly LabAttempt $attempt) {}
}
