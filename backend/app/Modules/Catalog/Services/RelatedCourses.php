<?php

namespace App\Modules\Catalog\Services;

use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Courses to suggest next to a course, most relevant first:
 * prerequisites, then courses that build on this one, then the same domain, then the same difficulty.
 */
final class RelatedCourses
{
    /** @return Collection<int, Course> */
    public function for(Course $course, int $limit = 3): Collection
    {
        $base = fn () => Course::query()->published()->whereKeyNot($course->id)->with('domain')->withLessonStats();

        $picked = $base()->whereIn('id', $course->prerequisites()->pluck('courses.id'))->get();

        $picked = $picked->merge(
            $base()->whereHas('prerequisites', fn (Builder $q) => $q->whereKey($course->id))->get()
        );

        if ($picked->count() < $limit) {
            $picked = $picked->merge(
                $base()->whereKeyNot($picked->modelKeys())
                    ->where('domain_id', $course->domain_id)
                    ->get()
            );
        }

        if ($picked->count() < $limit) {
            $picked = $picked->merge(
                $base()->whereKeyNot($picked->modelKeys())
                    ->where('difficulty', $course->difficulty)
                    ->orderByDesc('published_at')
                    ->limit($limit - $picked->count())
                    ->get()
            );
        }

        return $picked->unique('id')->take($limit)->values();
    }
}
