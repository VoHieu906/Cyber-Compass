<?php

namespace App\Modules\Content\Services;

use App\Models\Lesson;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** The admin lesson list query, and loading one lesson for the editor. */
final class LessonDirectory
{
    public const DEFAULT_PER_PAGE = 20;

    /** Block types that count as visuals in the list (Part C adds the generic visual blocks). */
    public const VISUAL_TYPES = ['diagram', 'media'];

    /**
     * @param  array{q?: ?string, filter?: array<string, mixed>, sort?: ?string, per_page?: ?int}  $params
     * @return LengthAwarePaginator<int, Lesson>
     */
    public function search(array $params): LengthAwarePaginator
    {
        $filter = $params['filter'] ?? [];

        $query = Lesson::query()
            ->select('lessons.*')
            ->with(['chapter.course:id,title', 'draft.editor:id,name', 'editor:id,name'])
            ->withCount(['sections', 'sections as visual_count' => fn (Builder $q) => $q->whereIn('type', self::VISUAL_TYPES)]);

        if ($term = trim((string) ($params['q'] ?? ''))) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('lessons.title', 'like', $like)->orWhere('lessons.slug', 'like', $like));
        }

        $query
            ->when($filter['course'] ?? null, fn (Builder $q, $id) => $q->whereHas('chapter', fn (Builder $c) => $c->where('course_id', $id)))
            ->when($filter['chapter'] ?? null, fn (Builder $q, $id) => $q->where('course_chapter_id', $id))
            ->when($filter['status'] ?? null, fn (Builder $q, $status) => $q->where('lessons.status', $status));

        if (array_key_exists('has_draft', $filter) && $filter['has_draft'] !== null) {
            filter_var($filter['has_draft'], FILTER_VALIDATE_BOOLEAN) ? $query->has('draft') : $query->doesntHave('draft');
        }

        match ($params['sort'] ?? '-updated_at') {
            'title' => $query->orderBy('lessons.title'),
            'course' => $query
                ->join('course_chapters', 'course_chapters.id', '=', 'lessons.course_chapter_id')
                ->join('courses', 'courses.id', '=', 'course_chapters.course_id')
                ->orderBy('courses.title')->orderBy('course_chapters.position')->orderBy('lessons.position'),
            default => $query->orderByDesc('lessons.updated_at'),
        };

        return $query->orderBy('lessons.id')->paginate($params['per_page'] ?? self::DEFAULT_PER_PAGE)->withQueryString();
    }

    /** Loads what AdminLessonResource reads. */
    public static function forEditor(Lesson $lesson): Lesson
    {
        return $lesson->load(['chapter.course', 'draft.editor:id,name', 'topics:id,slug,title']);
    }
}
