<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A personal roadmap version: a snapshot of topics (ARCHITECTURE §6.2, D4). */
#[Fillable(['user_id', 'career_id', 'assessment_attempt_id', 'version', 'status', 'generated_at'])]
#[Hidden(['active_marker'])]
class LearningPath extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Career, $this> */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    /** @return BelongsTo<AssessmentAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id');
    }

    /** @return HasMany<LearningPathNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(LearningPathNode::class)->orderBy('stage')->orderBy('position');
    }

    /** @param  Builder<LearningPath>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'version' => 'integer'];
    }
}
