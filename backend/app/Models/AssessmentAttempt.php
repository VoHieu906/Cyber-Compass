<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'assessment_id', 'started_at', 'submitted_at', 'score_percent'])]
#[Hidden(['open_marker'])]
class AssessmentAttempt extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * The questions this attempt drew, in the order they are asked.
     *
     * @return HasMany<AssessmentAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class)->orderBy('position');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /** @param  Builder<AssessmentAttempt>  $query */
    public function scopeSubmitted(Builder $query): void
    {
        $query->whereNotNull('submitted_at');
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score_percent' => 'integer',
        ];
    }
}
