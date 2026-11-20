<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unpublished edits to a lesson: a validated snapshot of its metadata and ordered sections.
 * The live lesson rows change only when the draft is published (docs/PHASE_3_5_CONTENT_AND_ADMIN.md §5.1).
 */
#[Fillable(['lesson_id', 'snapshot', 'lock_version', 'updated_by'])]
class LessonDraft extends Model
{
    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'lock_version' => 'integer'];
    }
}
