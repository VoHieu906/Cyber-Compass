<?php

namespace App\Models;

use App\Enums\ExerciseQuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One exercise question. `payload` is what the learner sees; `answer` is the key, hidden on
 * the model and never serialized before submission.
 */
#[Fillable(['exercise_id', 'key', 'position', 'type', 'prompt', 'payload', 'answer', 'explanation', 'lesson_section_id', 'is_archived'])]
#[Hidden(['answer'])]
class ExerciseQuestion extends Model
{
    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /** @return BelongsTo<LessonSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(LessonSection::class, 'lesson_section_id');
    }

    protected function casts(): array
    {
        return [
            'type' => ExerciseQuestionType::class,
            'payload' => 'array',
            'answer' => 'array',
            'position' => 'integer',
            'is_archived' => 'boolean',
        ];
    }
}
