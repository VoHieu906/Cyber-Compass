<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exercise_attempt_id', 'exercise_question_id', 'response', 'is_correct'])]
class ExerciseAttemptAnswer extends Model
{
    /** @return BelongsTo<ExerciseAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExerciseAttempt::class, 'exercise_attempt_id');
    }

    /** @return BelongsTo<ExerciseQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ExerciseQuestion::class, 'exercise_question_id');
    }

    protected function casts(): array
    {
        return ['response' => 'array', 'is_correct' => 'boolean'];
    }
}
