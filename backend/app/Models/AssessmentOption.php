<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// `is_correct` is hidden so a model serialized by mistake can never leak the answer key.
#[Fillable(['assessment_question_id', 'label', 'is_correct', 'position'])]
#[Hidden(['is_correct'])]
class AssessmentOption extends Model
{
    /** @return BelongsTo<AssessmentQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'assessment_question_id');
    }

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }
}
