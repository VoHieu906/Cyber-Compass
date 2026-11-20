<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One block of a lesson. `type` is a block type key validated by
 * App\Modules\Content\Blocks\BlockRegistry (the single source of allowed types);
 * `uuid` is the stable identity that survives reordering and edits.
 */
#[Fillable(['uuid', 'lesson_id', 'position', 'type', 'heading', 'payload'])]
class LessonSection extends Model
{
    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
