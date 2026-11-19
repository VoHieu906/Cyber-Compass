<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_chapter_id', 'slug', 'title', 'summary', 'objectives', 'key_takeaways', 'resources', 'estimated_minutes', 'position'])]
class Lesson extends Model
{
    /** @return BelongsTo<CourseChapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(CourseChapter::class, 'course_chapter_id');
    }

    /** @return HasMany<LessonSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(LessonSection::class)->orderBy('position');
    }

    /** @return BelongsToMany<Topic, $this> */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'topic_lessons')->withPivot('position', 'is_required');
    }

    /** @return HasMany<LessonProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'key_takeaways' => 'array',
            'resources' => 'array',
        ];
    }
}
