<?php

namespace App\Models;

use App\Enums\LessonSectionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lesson_id', 'position', 'type', 'heading', 'payload'])]
class LessonSection extends Model
{
    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    protected function casts(): array
    {
        return [
            'type' => LessonSectionType::class,
            'payload' => 'array',
        ];
    }
}
