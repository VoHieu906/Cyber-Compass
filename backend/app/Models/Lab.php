<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A practical lab (ARCHITECTURE §7). `scenario` holds checkpoint answers and is never
 * serialized; the engine decides what of it the learner sees.
 */
#[Fillable(['slug', 'title', 'summary', 'briefing', 'domain_id', 'lesson_id', 'engine', 'provider', 'scenario', 'difficulty', 'estimated_minutes', 'position', 'is_archived'])]
#[Hidden(['scenario'])]
class Lab extends Model
{
    protected $attributes = ['provider' => 'simulated', 'is_archived' => false];

    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return HasMany<LabAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(LabAttempt::class);
    }

    /** @param  Builder<Lab>  $query */
    public function scopeOffered(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    protected function casts(): array
    {
        return [
            'scenario' => 'array',
            'difficulty' => 'integer',
            'estimated_minutes' => 'integer',
            'position' => 'integer',
            'is_archived' => 'boolean',
        ];
    }
}
