<?php

namespace App\Modules\Assessment\Events;

use App\Models\AssessmentAttempt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An attempt was scored. Dispatched after the transaction commits. Phase 6 listens to it to
 * record the learning activity and send the assessment-result notification (ARCHITECTURE §3.2).
 */
final class AssessmentSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly AssessmentAttempt $attempt) {}
}
