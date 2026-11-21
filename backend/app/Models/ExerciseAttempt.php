<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A submitted exercise attempt. Attempts are scored on submission; there is no draft on the server. */
#[Fillable(['user_id', 'exercise_id', 'score_percent', 'passed', 'time_spent_seconds', 'submitted_at'])]
class ExerciseAttempt extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /** @return HasMany<ExerciseAttemptAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(ExerciseAttemptAnswer::class);
    }

    protected function casts(): array
    {
        return [
            'score_percent' => 'integer',
            'passed' => 'boolean',
            'time_spent_seconds' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }
}
