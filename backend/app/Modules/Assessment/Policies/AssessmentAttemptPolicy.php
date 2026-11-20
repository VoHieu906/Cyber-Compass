<?php

namespace App\Modules\Assessment\Policies;

use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Attempts are private. Another learner's attempt answers 404, so its existence is not revealed. */
final class AssessmentAttemptPolicy
{
    public function view(User $user, AssessmentAttempt $attempt): Response
    {
        return $attempt->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
