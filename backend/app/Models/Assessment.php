<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'title', 'description', 'is_active', 'time_limit_minutes'])]
class Assessment extends Model
{
    /** @return HasMany<AssessmentQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('position');
    }

    /**
     * Questions a new attempt draws: every question still in the content file.
     *
     * @return HasMany<AssessmentQuestion, $this>
     */
    public function currentQuestions(): HasMany
    {
        return $this->questions()->where('is_archived', false);
    }

    /** @param  Builder<Assessment>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
