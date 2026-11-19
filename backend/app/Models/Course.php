<?php

namespace App\Models;

use App\Enums\Difficulty;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['domain_id', 'slug', 'title', 'summary', 'description', 'difficulty', 'thumbnail_path', 'objectives', 'is_published', 'published_at'])]
class Course extends Model
{
    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** @return HasMany<CourseChapter, $this> */
    public function chapters(): HasMany
    {
        return $this->hasMany(CourseChapter::class)->orderBy('position');
    }

    /** @return HasManyThrough<Lesson, CourseChapter, $this> */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, CourseChapter::class);
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'course_skills');
    }

    /** @return BelongsToMany<Course, $this> */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'course_id', 'prerequisite_course_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_favorites');
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @param  Builder<Course>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Lesson count and total minutes, aggregated in the query rather than stored.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeWithLessonStats(Builder $query): void
    {
        $query->withCount('lessons')->withSum('lessons as estimated_minutes', 'estimated_minutes');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
            'objectives' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
