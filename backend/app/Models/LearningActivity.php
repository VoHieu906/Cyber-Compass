<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One thing a learner did, appended by the Progress listeners after commit (D33). Never updated.
 * The subject is a lesson, lab, exercise attempt or assessment attempt (morph aliases in
 * AppServiceProvider).
 */
#[Fillable(['user_id', 'type', 'subject_type', 'subject_id', 'duration_seconds', 'occurred_at'])]
class LearningActivity extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'duration_seconds' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
