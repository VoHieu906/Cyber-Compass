<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The published architecture of a lesson (F3, ARCHITECTURE D57): the problem it solves and,
 * optionally, its shape with sub-nodes. Written only by publishing and the content importer;
 * drafts and revisions carry it inside the lesson snapshot.
 */
#[Fillable(['lesson_id', 'architecture'])]
class LessonArchitecture extends Model
{
    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    protected function casts(): array
    {
        return ['architecture' => 'array'];
    }
}
