<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course's map: its big problem, layout and typed lesson relations (F3, ARCHITECTURE D57).
 * Saved live by admins (audited, restorable, D58); `updated_by` makes the database its owner.
 */
#[Fillable(['course_id', 'graph', 'lock_version', 'updated_by'])]
class CourseGraph extends Model
{
    protected $attributes = ['lock_version' => 1, 'updated_by' => null];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    protected function casts(): array
    {
        return ['graph' => 'array', 'lock_version' => 'integer'];
    }
}
