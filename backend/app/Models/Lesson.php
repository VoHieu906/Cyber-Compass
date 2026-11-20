<?php

namespace App\Models;

use App\Enums\LessonStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'course_chapter_id', 'slug', 'title', 'summary', 'objectives', 'key_takeaways', 'resources',
    'estimated_minutes', 'position', 'status', 'published_at', 'created_by', 'updated_by',
])]
class Lesson extends Model
{
    protected $attributes = ['status' => 'published'];

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

    /**
     * Unpublished edits, if an admin is working on this lesson.
     *
     * @return HasOne<LessonDraft, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(LessonDraft::class);
    }

    /** @return HasMany<LessonRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(LessonRevision::class)->orderByDesc('version');
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

    /**
     * Published lessons only. Use inside queries that already start from a published course
     * (lesson counts on a course, a course's curriculum).
     *
     * @param  Builder<Lesson>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('lessons.status', LessonStatus::Published->value);
    }

    /**
     * What a learner may see: a published lesson in a published course. Every learner-facing
     * query (reader, progress, navigation, counts, roadmaps, search) must go through this or
     * scopePublished, so drafts and archived lessons never leak.
     *
     * @param  Builder<Lesson>  $query
     */
    public function scopeVisibleToLearners(Builder $query): void
    {
        $query->published()->whereHas('chapter.course', fn (Builder $q) => $q->where('is_published', true));
    }

    public function isVisibleToLearners(): bool
    {
        return $this->status === LessonStatus::Published && $this->chapter->course->is_published;
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
            'status' => LessonStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
