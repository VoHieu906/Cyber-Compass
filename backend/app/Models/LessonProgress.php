<?php

namespace App\Models;

use App\Enums\LessonProgressStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'lesson_id', 'status', 'started_at', 'completed_at', 'time_spent_seconds'])]
class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    protected function casts(): array
    {
        return [
            'status' => LessonProgressStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
