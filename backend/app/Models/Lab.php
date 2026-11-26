<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A practical lab (ARCHITECTURE §7). `scenario` holds checkpoint answers and is never
 * serialized; the engine decides what of it the learner sees.
 */
#[Fillable(['slug', 'title', 'summary', 'briefing', 'domain_id', 'lesson_id', 'engine', 'provider', 'scenario', 'solutions', 'status', 'updated_by', 'scenario_version', 'lock_version', 'difficulty', 'estimated_minutes', 'position', 'is_archived'])]
#[Hidden(['scenario', 'solutions'])]
class Lab extends Model
{
    protected $attributes = ['provider' => 'simulated', 'status' => 'published', 'scenario_version' => 1, 'lock_version' => 1, 'is_archived' => false];

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

    /** @return HasOne<LabDraft, $this> */
    public function draft(): HasOne
    {
        return $this->hasOne(LabDraft::class);
    }

    /** @return HasMany<LabRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(LabRevision::class);
    }

    /** What learners may see: published and not archived. Every learner query goes through this. @param  Builder<Lab>  $query */
    public function scopeOffered(Builder $query): void
    {
        $query->where('is_archived', false)->where('status', 'published');
    }

    protected function casts(): array
    {
        return [
            'scenario' => 'array',
            'solutions' => 'array',
            'scenario_version' => 'integer',
            'lock_version' => 'integer',
            'difficulty' => 'integer',
            'estimated_minutes' => 'integer',
            'position' => 'integer',
            'is_archived' => 'boolean',
        ];
    }
}
