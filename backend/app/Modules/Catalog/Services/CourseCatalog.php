<?php

namespace App\Modules\Catalog\Services;

use App\Enums\Difficulty;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The course catalog query: search, filters, sorting and pagination.
 * Search is a case-insensitive substring match on title and summary. At catalog
 * scale this is predictable (it matches "net" in "Networking"), which MySQL
 * full-text search with its 3-character minimum and whole-word matching is not.
 */
final class CourseCatalog
{
    public const DEFAULT_PER_PAGE = 6;

    /**
     * @param  array{q?: ?string, filter?: array{domain?: ?string, difficulty?: ?string, favorited?: mixed}, sort?: ?string, per_page?: ?int}  $params
     * @return LengthAwarePaginator<int, Course>
     */
    public function search(array $params, ?User $viewer): LengthAwarePaginator
    {
        $query = Course::query()
            ->published()
            ->with('domain')
            ->withLessonStats();

        if ($viewer) {
            $query->withExists(['favoritedBy as is_favorited' => fn (Builder $q) => $q->whereKey($viewer->getKey())]);
        }

        if ($term = trim((string) ($params['q'] ?? ''))) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like));
        }

        $filter = $params['filter'] ?? [];

        if (! empty($filter['domain'])) {
            $query->whereHas('domain', fn (Builder $q) => $q->where('slug', $filter['domain']));
        }

        if (! empty($filter['difficulty'])) {
            $query->where('difficulty', Difficulty::fromSlug($filter['difficulty'])?->value);
        }

        if (filter_var($filter['favorited'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            // Favorites only exist for a signed-in viewer; for guests the filter matches nothing.
            $viewer
                ? $query->whereHas('favoritedBy', fn (Builder $q) => $q->whereKey($viewer->getKey()))
                : $query->whereRaw('1 = 0');
        }

        $this->sort($query, $params['sort'] ?? 'newest');

        return $query->paginate($params['per_page'] ?? self::DEFAULT_PER_PAGE)->withQueryString();
    }

    /** @param  Builder<Course>  $query */
    private function sort(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        match (ltrim($sort, '-')) {
            'title' => $query->orderBy('title', $direction),
            'difficulty' => $query->orderBy('difficulty', $direction)->orderBy('title'),
            'duration' => $query->orderBy('estimated_minutes', $direction)->orderBy('title'),
            default => $query->orderByDesc('published_at')->orderBy('id'),
        };
    }
}
