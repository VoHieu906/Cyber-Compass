<?php

namespace App\Modules\Assessment\Actions;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Starts an attempt, or resumes the learner's open one.
 *
 * A new attempt records the questions it draws as unanswered answer rows, so the attempt keeps
 * its own question set even if the bank changes later (and a larger, randomly drawn pool can be
 * added without a schema change). The unique index on the open marker allows one open attempt
 * per learner; a second request racing the first resumes the attempt the first one created.
 */
final class StartAttempt
{
    /** @return array{attempt: AssessmentAttempt, created: bool} */
    public function __invoke(User $user, Assessment $assessment): array
    {
        if ($open = $this->openAttempt($user, $assessment)) {
            return ['attempt' => $open, 'created' => false];
        }

        try {
            $attempt = DB::transaction(function () use ($user, $assessment) {
                $attempt = $user->assessmentAttempts()->create([
                    'assessment_id' => $assessment->id,
                    'started_at' => now(),
                ]);
                $attempt->answers()->createMany($assessment->currentQuestions()->pluck('id')->values()->map(
                    fn (int $questionId, int $i) => ['assessment_question_id' => $questionId, 'position' => $i + 1],
                ));

                return $attempt;
            });
        } catch (UniqueConstraintViolationException) {
            return ['attempt' => $this->openAttempt($user, $assessment), 'created' => false];
        }

        return ['attempt' => $attempt, 'created' => true];
    }

    private function openAttempt(User $user, Assessment $assessment): ?AssessmentAttempt
    {
        return $user->assessmentAttempts()
            ->where('assessment_id', $assessment->id)
            ->whereNull('submitted_at')
            ->first();
    }
}
