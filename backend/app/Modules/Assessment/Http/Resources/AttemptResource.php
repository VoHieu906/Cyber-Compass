<?php

namespace App\Modules\Assessment\Http\Resources;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An attempt as the learner takes it: questions in the order asked, their options and the saved
 * choice. It never contains the answer key; the result endpoint reveals that after submission.
 *
 * @mixin AssessmentAttempt
 */
final class AttemptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $answers = $this->answers;

        return [
            'id' => $this->id,
            'status' => $this->isSubmitted() ? 'submitted' : 'in_progress',
            'assessment' => ['slug' => $this->assessment->slug, 'title' => $this->assessment->title],
            'started_at' => $this->started_at->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'total' => $answers->count(),
            'answered' => $answers->whereNotNull('assessment_option_id')->count(),
            'questions' => $answers->map(fn (AssessmentAnswer $answer) => [
                'id' => $answer->question->id,
                'position' => $answer->position,
                'domain' => ['slug' => $answer->question->domain->slug, 'name' => $answer->question->domain->name],
                'difficulty' => $answer->question->difficulty,
                'prompt' => $answer->question->prompt,
                'code' => $answer->question->code,
                'options' => $answer->question->options->map(fn (AssessmentOption $option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                ])->all(),
                'selected_option_id' => $answer->assessment_option_id,
            ])->all(),
        ];
    }
}
