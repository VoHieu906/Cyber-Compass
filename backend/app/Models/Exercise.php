<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A lesson's exercise (ARCHITECTURE D29). At most one per lesson. */
#[Fillable(['lesson_id', 'pass_percent'])]
class Exercise extends Model
{
    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * The questions learners are asked, in order. Archived questions stay for past attempts.
     *
     * @return HasMany<ExerciseQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ExerciseQuestion::class)->where('is_archived', false)->orderBy('position');
    }

    /** @return HasMany<ExerciseQuestion, $this> */
    public function allQuestions(): HasMany
    {
        return $this->hasMany(ExerciseQuestion::class);
    }

    /** @return HasMany<ExerciseAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    protected function casts(): array
    {
        return ['pass_percent' => 'integer'];
    }
}
