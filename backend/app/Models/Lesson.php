<?php

namespace App\Models;

use App\Enums\LessonStatus;
use App\Modules\Content\Documents\BlockDocument;
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
class Lesson extends Model implements BlockDocument
{
    public const MAX_SECTIONS = 60;

    /** Mirrors the column defaults, so a model created in memory has them without a reload (strict mode). */
    protected $attributes = ['status' => 'published', 'published_at' => null, 'created_by' => null, 'updated_by' => null];

    /** @return BelongsTo<CourseChapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(CourseChapter::class, 'course_chapter_id');
    }

    /**
     * The lesson's exercise, if it has one (ARCHITECTURE D29).
     *
     * @return HasOne<Exercise, $this>
     */
    public function exercise(): HasOne
    {
        return $this->hasOne(Exercise::class);
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

    /**
     * The admin who last changed the live lesson (null for seeded content). Its presence also
     * makes the database the lesson's owner for content imports (ARCHITECTURE D19).
     *
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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

    public function documentType(): string
    {
        return 'lesson';
    }

    public function maxSections(): int
    {
        return self::MAX_SECTIONS;
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
