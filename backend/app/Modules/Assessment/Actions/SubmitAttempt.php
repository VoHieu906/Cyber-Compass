<?php

namespace App\Modules\Assessment\Actions;

use App\Models\AssessmentAttempt;
use App\Modules\Assessment\Events\AssessmentSubmitted;
use App\Modules\Assessment\Services\ScoringService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Scores an attempt in one transaction: marks every answer correct or not (unanswered counts as
 * incorrect), stores the overall score as an immutable snapshot and stamps the submission time.
 * Domain scores are not stored; they are computed from the answers (DATABASE_SCHEMA).
 */
final class SubmitAttempt
{
    public function __construct(private readonly ScoringService $scoring) {}

    public function __invoke(AssessmentAttempt $attempt): AssessmentAttempt
    {
        $submitted = DB::transaction(function () use ($attempt) {
            $locked = AssessmentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->isSubmitted()) {
                throw new ConflictHttpException(__('This attempt has already been submitted.'));
            }

            $answers = $locked->answers()->with(['question.domain', 'option'])->get();
            foreach ($answers as $answer) {
                $answer->update(['is_correct' => (bool) $answer->option?->is_correct]);
            }

            $score = $this->scoring->score($answers->map(fn ($answer) => [
                'domain' => $answer->question->domain->slug,
                'difficulty' => $answer->question->difficulty,
                'correct' => $answer->is_correct,
                'answered' => $answer->assessment_option_id !== null,
            ]));

            $locked->update(['submitted_at' => now(), 'score_percent' => $score['overall']['score']]);

            return $locked;
        });

        AssessmentSubmitted::dispatch($submitted);

        return $submitted;
    }
}
