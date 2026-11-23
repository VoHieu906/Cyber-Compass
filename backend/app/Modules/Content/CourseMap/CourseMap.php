<?php

namespace App\Modules\Content\CourseMap;

use App\Enums\LessonStatus;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Lesson;
use Illuminate\Support\Collection;

/**
 * Turns a stored course map and the lessons' architectures into what readers see, and lists
 * what an author should fix (F3, D57). Readers only ever get lessons a learner may see: the
 * caller passes the course with its published lessons, and every node, edge, placement and
 * sub-node link naming anything else is dropped here, on the server.
 */
final class CourseMap
{
    /**
     * The map for the public course page, or null when the course has none.
     * Expects `graph` and `chapters.lessons.architecture` loaded, lessons already limited to
     * published ones of a published course (CourseController::show).
     *
     * @return array<string, mixed>|null
     */
    public function forLearners(Course $course): ?array
    {
        $stored = $course->graph?->graph;
        if ($stored === null) {
            return null;
        }
        [, $graph] = MapVersions::upgrade($stored, 'graph');

        /** @var Collection<int, Lesson> $lessons */
        $lessons = $course->chapters->flatMap(fn (CourseChapter $c) => $c->lessons)->values();
        $visible = $lessons->pluck('slug')->flip();
        $placement = $graph['layout'] === 'manual' ? ($graph['placement'] ?? []) : [];

        return [
            'problem' => $graph['problem'],
            'layout' => $graph['layout'],
            'lanes' => $graph['lanes'] ?? [],
            'lessons' => $lessons->map(function (Lesson $lesson) use ($visible, $placement) {
                $arch = $lesson->architecture?->architecture;
                if ($arch !== null) {
                    [, $arch] = MapVersions::upgrade($arch, 'architecture');
                }

                return [
                    'slug' => $lesson->slug,
                    'title' => $lesson->title,
                    'placement' => $placement[$lesson->slug] ?? null,
                    'problem' => $arch['problem'] ?? null,
                    'shape' => $arch['shape'] ?? null,
                    'rows' => $arch['rows'] ?? null,
                    'cols' => $arch['cols'] ?? null,
                    'nodes' => array_map(function (array $node) use ($visible, $lesson) {
                        if (isset($node['lesson']) && ($node['lesson'] === $lesson->slug || ! $visible->has($node['lesson']))) {
                            unset($node['lesson']);
                        }

                        return $node;
                    }, $arch['nodes'] ?? []),
                    'edges' => $arch['edges'] ?? [],
                ];
            })->all(),
            'edges' => array_values(array_filter(
                $graph['edges'],
                fn (array $e) => $visible->has($e['from']) && $visible->has($e['to']),
            )),
        ];
    }

    /**
     * What the author should look at; never a reason to refuse a save (F3 integrity rules).
     * Types: `unplaced` (manual layout, a live lesson with no place), `unconnected` (a live
     * lesson with no relation), `dangling` (the map names a lesson that is not live in this
     * course), `link_outside` (a sub-node links to such a lesson), `no_problem` (a live lesson
     * whose problem is not written yet).
     *
     * @param  array<string, mixed>|null  $graph  defaults to the stored map
     * @return list<array{type: string, lesson: string, detail?: string}>
     */
    public function issues(Course $course, ?array $graph = null): array
    {
        $graph ??= $course->graph()->first()?->graph;
        $live = Lesson::query()
            ->whereIn('course_chapter_id', $course->chapters()->pluck('id'))
            ->where('status', LessonStatus::Published)
            ->with('architecture')
            ->orderBy('course_chapter_id')->orderBy('position')
            ->get();

        return self::findIssues(
            $graph,
            $live->pluck('slug')->all(),
            $live->mapWithKeys(fn (Lesson $l) => [$l->slug => $l->architecture?->architecture])->all(),
        );
    }

    /**
     * Lesson slugs a lesson's sub-nodes link to that are not live in the given course.
     *
     * @param  array<string, mixed>|null  $architecture
     * @return list<string>
     */
    public static function hiddenLinks(?array $architecture, int $courseId): array
    {
        $linked = array_values(array_unique(array_filter(array_column($architecture['nodes'] ?? [], 'lesson'))));
        if ($linked === []) {
            return [];
        }
        $live = Lesson::query()->whereIn('slug', $linked)->published()
            ->whereHas('chapter', fn ($q) => $q->where('course_id', $courseId))->pluck('slug')->all();

        return array_values(array_diff($linked, $live));
    }

    /**
     * The rule itself, on plain data, so the importer can run it on a content file.
     *
     * @param  array<string, mixed>|null  $graph
     * @param  list<string>  $liveSlugs  in course order
     * @param  array<string, array<string, mixed>|null>  $architectures  slug => architecture
     * @return list<array{type: string, lesson: string, detail?: string}>
     */
    public static function findIssues(?array $graph, array $liveSlugs, array $architectures): array
    {
        $issues = [];
        $live = array_flip($liveSlugs);

        if ($graph !== null) {
            $connected = [];
            foreach ($graph['edges'] ?? [] as $edge) {
                foreach (['from', 'to'] as $end) {
                    if (! isset($live[$edge[$end]])) {
                        $issues[] = ['type' => 'dangling', 'lesson' => $edge[$end], 'detail' => 'edge'];
                    }
                    $connected[$edge[$end]] = true;
                }
            }
            foreach (array_keys($graph['placement'] ?? []) as $slug) {
                if (! isset($live[$slug])) {
                    $issues[] = ['type' => 'dangling', 'lesson' => $slug, 'detail' => 'placement'];
                }
            }
            foreach ($liveSlugs as $slug) {
                if (($graph['layout'] ?? 'auto') === 'manual' && ! isset($graph['placement'][$slug])) {
                    $issues[] = ['type' => 'unplaced', 'lesson' => $slug];
                }
                if (count($liveSlugs) > 1 && ! isset($connected[$slug])) {
                    $issues[] = ['type' => 'unconnected', 'lesson' => $slug];
                }
            }
        }

        foreach ($liveSlugs as $slug) {
            $arch = $architectures[$slug] ?? null;
            if ($arch === null) {
                // Only worth saying on a course that shows a map.
                $graph !== null && $issues[] = ['type' => 'no_problem', 'lesson' => $slug];

                continue;
            }
            foreach ($arch['nodes'] ?? [] as $node) {
                if (isset($node['lesson']) && ! isset($live[$node['lesson']])) {
                    $issues[] = ['type' => 'link_outside', 'lesson' => $slug, 'detail' => $node['lesson']];
                }
            }
        }

        return array_values(array_unique($issues, SORT_REGULAR));
    }
}
