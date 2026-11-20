<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_id', 'domain_id', 'key', 'difficulty', 'prompt', 'code', 'explanation', 'position', 'is_archived'])]
class AssessmentQuestion extends Model
{
    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** @return HasMany<AssessmentOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(AssessmentOption::class)->orderBy('position');
    }

    protected function casts(): array
    {
        return ['difficulty' => 'integer', 'is_archived' => 'boolean'];
    }
}
