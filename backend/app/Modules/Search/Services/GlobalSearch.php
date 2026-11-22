<?php

namespace App\Modules\Search\Services;

use App\Models\Career;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Post;
use App\Support\Search\Like;

/**
 * Header search over courses, lessons, careers and blog posts (D36, F2), grouped, at most five of each.
 * Matching is the catalog's substring rule (D15) on title and summary; titles that start with the
 * term come first. Only published courses and careers, and only lessons learners may see
 * (`Lesson::scopeVisibleToLearners`) and posts readers may see (`Post::scopeVisibleToReaders`), so
 * drafts never leak through search.
 */
final class GlobalSearch
{
    public const PER_GROUP = 5;

    /** @return array{courses: list<array<string, mixed>>, lessons: list<array<string, mixed>>, careers: list<array<string, mixed>>, posts: list<array<string, mixed>>} */
    public function search(string $term): array
    {
        $startsWith = addcslashes($term, '%_\\').'%';
        $rank = fn ($query, string $column) => $query->orderByRaw("case when {$column} like ? then 0 else 1 end", [$startsWith])->orderBy($column);

        $courses = $rank(Like::anyColumn(Course::query()->published()->with('domain:id,slug,name'), ['title', 'summary'], $term), 'title')
            ->limit(self::PER_GROUP)
            ->get(['id', 'slug', 'title', 'summary', 'domain_id']);

        $lessons = $rank(Like::anyColumn(Lesson::query()->visibleToLearners()->with('chapter.course:id,slug,title'), ['lessons.title', 'lessons.summary'], $term), 'lessons.title')
            ->limit(self::PER_GROUP)
            ->get(['lessons.id', 'lessons.slug', 'lessons.title', 'lessons.summary', 'lessons.course_chapter_id']);

        $careers = $rank(Like::anyColumn(Career::query()->published(), ['title', 'summary'], $term), 'title')
            ->limit(self::PER_GROUP)
            ->get(['id', 'slug', 'title', 'summary']);

        $posts = $rank(Like::anyColumn(Post::query()->visibleToReaders(), ['posts.title', 'posts.summary'], $term), 'posts.title')
            ->limit(self::PER_GROUP)
            ->get(['id', 'slug', 'title', 'summary']);

        return [
            'courses' => $courses->map(fn (Course $c) => [
                'slug' => $c->slug,
                'title' => $c->title,
                'summary' => $c->summary,
                'domain' => $c->domain?->name,
            ])->all(),
            'lessons' => $lessons->map(fn (Lesson $l) => [
                'slug' => $l->slug,
                'title' => $l->title,
                'summary' => $l->summary,
                'course' => $l->chapter->course->title,
            ])->all(),
            'careers' => $careers->map(fn (Career $c) => [
                'slug' => $c->slug,
                'title' => $c->title,
                'summary' => $c->summary,
            ])->all(),
            'posts' => $posts->map(fn (Post $p) => [
                'slug' => $p->slug,
                'title' => $p->title,
                'summary' => $p->summary,
            ])->all(),
        ];
    }
}
