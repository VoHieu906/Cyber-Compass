<?php

namespace App\Modules\Progress\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A learner reached a milestone for the first time (D34): `course` (every lesson of a course),
 * `roadmap` (25, 50, 75 or 100% of the active roadmap) or `streak` (7, 30 or 100 days).
 * Dispatched by `MilestoneDetector`, which already runs after commit.
 */
final class MilestoneReached
{
    use Dispatchable;

    /** @param  array<string, string|int>  $params  `kind` plus what the message names */
    public function __construct(
        public readonly User $user,
        public readonly array $params,
        public readonly string $url,
    ) {}
}
