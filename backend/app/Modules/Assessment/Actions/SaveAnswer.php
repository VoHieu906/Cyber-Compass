<?php

namespace App\Modules\Assessment\Actions;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Autosaves one answer of an open attempt. A null option clears the answer. */
final class SaveAnswer
{
    public function __invoke(AssessmentAttempt $attempt, int $questionId, ?int $optionId): AssessmentAnswer
    {
        return DB::transaction(function () use ($attempt, $questionId, $optionId) {
            // Locking the attempt serialises saves with submission, so no answer lands after scoring.
            $locked = AssessmentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->isSubmitted()) {
                throw new ConflictHttpException(__('This attempt has already been submitted.'));
            }

            $answer = $locked->answers()->where('assessment_question_id', $questionId)->with('question')->first()
                ?? throw new NotFoundHttpException;

            if ($optionId !== null && ! $answer->question->options()->whereKey($optionId)->exists()) {
                throw ValidationException::withMessages(['option_id' => __('Choose one of the options for this question.')]);
            }

            $answer->update(['assessment_option_id' => $optionId]);

            return $answer;
        });
    }
}
