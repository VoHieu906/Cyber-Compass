<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A learner's working state in a lab (one per learner and lab; reset starts it over). */
#[Fillable(['user_id', 'lab_id', 'state', 'state_version', 'action_count', 'checkpoints_passed', 'completed_at'])]
class LabAttempt extends Model
{
    protected $attributes = ['state_version' => 1, 'action_count' => 0, 'completed_at' => null];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lab, $this> */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'checkpoints_passed' => 'array',
            'state_version' => 'integer',
            'action_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
