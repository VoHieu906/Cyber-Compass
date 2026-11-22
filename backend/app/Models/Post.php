<?php

namespace App\Models;

use App\Enums\LessonStatus;
use App\Modules\Content\Documents\BlockDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A blog post (F2, ARCHITECTURE D55): a block document like a lesson, but never part of a
 * course, roadmap, progress or learning event. `cover` is a lesson_media uuid. Status uses the
 * lesson statuses (draft, published, archived).
 */
#[Fillable([
    'slug', 'title', 'summary', 'cover', 'tags', 'estimated_minutes', 'status', 'published_at', 'created_by', 'updated_by',
])]
class Post extends Model implements BlockDocument
{
    public const MAX_SECTIONS = 150;

    /** Mirrors the column defaults, so a model created in memory has them without a reload (strict mode). */
    protected $attributes = ['status' => 'draft', 'published_at' => null, 'cover' => null, 'created_by' => null, 'updated_by' => null];

    /** @return HasMany<PostSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(PostSection::class)->orderBy('position');
    }

    /** @return HasOne<PostDraft, $this> */
    public function draft(): HasOne
    {
        return $this->hasOne(PostDraft::class);
    }

    /** @return HasMany<PostRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->orderByDesc('version');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<LessonMedia, $this> */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(LessonMedia::class, 'cover', 'uuid');
    }

    /**
     * What a reader (guest or signed in) may see: published posts only. Every public query
     * (list, detail, search) goes through this, so drafts and archived posts never leak.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeVisibleToReaders(Builder $query): void
    {
        $query->where('posts.status', LessonStatus::Published->value)->whereNotNull('posts.published_at');
    }

    public function documentType(): string
    {
        return 'post';
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
            'tags' => 'array',
            'status' => LessonStatus::class,
            'published_at' => 'datetime',
            'estimated_minutes' => 'integer',
        ];
    }
}
