<?php

namespace App\Modules\Content\CourseMap;

use App\Enums\LessonStatus;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Lesson;

/**
 * Rule-based suggestions for keeping a course map current (F4 Part C, D63). Deterministic and
 * local: no AI, no network. Nothing here writes anything; an admin accepts a suggestion into the
 * panel's working copy and saves it the usual way (audited, lock_version checked, the map then
 * owned by the database). Only live (published) lessons are ever suggested, like `issues()`.
 *
 * Map suggestions, each next to the issue it resolves:
 * - `relation` (unconnected): `builds-on` from the previous lesson of its chapter, else
 *   `requires` from the last lesson of the previous chapter; the first lesson of the course
 *   points forward instead. Never a duplicate pair, never a cycle in the ordering relations.
 * - `placement` (unplaced, manual layout): the lane most of its chapter sits in, the first free
 *   cell after the previous lesson in that lane, inside the 8 × 10 grid. Lanes are never added.
 * - `start_map` (a course with lessons and no map): the course summary as the big problem, the
 *   automatic layout and the relation chain.
 * Lesson suggestions (for its architecture, accepted in the lesson draft):
 * - `problem` (no_problem): the lesson summary, as a draft to edit.
 * - `shape`: while the architecture is not started, every block whose knowledge already has a
 *   shape (BLOCK_SHAPES; a flow that loops back is a cycle). None when no block matches.
 */
final class CourseMapSuggestions
{
    public const BLOCK_SHAPES = [
        'layer-stack' => 'layers',
        'flow' => 'pipeline',
        'tree' => 'tree',
        'topology' => 'mesh',
        'comparison' => 'matrix',
    ];

    private const ORDERING = ['requires', 'builds-on', 'feeds-into'];

    private const COLS = 8;

    private const ROWS = 10;

    /**
     * Suggestions for a course's stored map (or the given one), for the admin panel.
     *
     * @param  array<string, mixed>|null  $graph  defaults to the stored map
     * @return list<array<string, mixed>>
     */
    public function forCourse(Course $course, ?array $graph = null): array
    {
        $graph ??= $course->graph()->first()?->graph;
        $chapters = $course->chapters()->orderBy('position')->with([
            'lessons' => fn ($q) => $q->where('status', LessonStatus::Published)->orderBy('position'),
            'lessons.architecture',
            'lessons.sections:id,lesson_id,uuid,position,type,heading,payload',
        ])->get();

        $lessons = $chapters->flatMap(fn (CourseChapter $c) => $c->lessons->map(fn (Lesson $l) => [
            'slug' => $l->slug,
            'chapter' => $c->id,
            'summary' => $l->summary,
            'architecture' => $l->architecture?->architecture,
            'sections' => $l->sections->sortBy('position')->map(fn ($s) => [
                'uuid' => $s->uuid, 'type' => $s->type, 'heading' => $s->heading, 'payload' => $s->payload,
            ])->values()->all(),
        ]))->values()->all();

        return self::find($graph, $lessons, (string) $course->summary);
    }

    /**
     * The rules on plain data (the importer and `content:check-graphs` use them on content files).
     *
     * @param  array<string, mixed>|null  $graph
     * @param  list<array{slug: string, chapter: int|string, summary?: string|null, architecture?: array<string, mixed>|null, sections?: list<array<string, mixed>>}>  $lessons  live lessons, in course order
     * @return list<array<string, mixed>>
     */
    public static function find(?array $graph, array $lessons, string $courseSummary): array
    {
        $suggestions = [];
        $slugs = array_column($lessons, 'slug');

        if ($graph === null) {
            if ($lessons !== []) {
                $edges = [];
                foreach ($lessons as $i => $lesson) {
                    if ($i > 0 && ($edge = self::relation($lesson['slug'], $lessons, $edges, forward: false)) !== null) {
                        $edges[] = $edge;
                    }
                }
                $suggestions[] = ['key' => 'start_map', 'type' => 'start_map', 'graph' => [
                    'version' => MapVersions::CURRENT,
                    'problem' => self::clip(trim($courseSummary) !== '' ? $courseSummary : $lessons[0]['slug'], 200),
                    'layout' => 'auto',
                    'edges' => $edges,
                ]];
            }
        } else {
            [, $graph] = MapVersions::upgrade($graph, 'graph');
            $issues = CourseMap::findIssues($graph, $slugs, []);
            $flagged = fn (string $type) => array_column(array_filter($issues, fn ($i) => $i['type'] === $type), 'lesson');

            // Each accepted relation is part of the map the next one is checked against, so "Accept all" stays acyclic.
            $edges = $graph['edges'] ?? [];
            foreach ($flagged('unconnected') as $slug) {
                if (self::connected($slug, $edges)) {
                    continue;
                }
                $edge = self::relation($slug, $lessons, $edges, forward: true);
                if ($edge !== null) {
                    $edges[] = $edge;
                    $suggestions[] = ['key' => "relation:{$slug}", 'type' => 'relation', 'lesson' => $slug, 'resolves' => 'unconnected', 'edge' => $edge];
                }
            }

            $placement = $graph['placement'] ?? [];
            $lanes = array_column($graph['lanes'] ?? [], 'id');
            foreach ($flagged('unplaced') as $slug) {
                $place = self::placement($slug, $lessons, $placement, $lanes);
                if ($place !== null) {
                    $placement[$slug] = $place;
                    $suggestions[] = ['key' => "placement:{$slug}", 'type' => 'placement', 'lesson' => $slug, 'resolves' => 'unplaced', 'placement' => $place];
                }
            }
        }

        foreach ($lessons as $lesson) {
            foreach (self::forLesson($lesson['summary'] ?? null, $lesson['sections'] ?? [], $lesson['architecture'] ?? null) as $s) {
                $suggestions[] = ['lesson' => $lesson['slug'], ...$s, 'key' => "{$s['type']}:{$lesson['slug']}".(isset($s['section']) ? ":{$s['section']}" : '')];
            }
        }

        return $suggestions;
    }

    /**
     * Suggestions for one lesson's architecture: its problem from the summary while it has none,
     * and a shape for every block that has one while the architecture has neither a shape nor a
     * problem (an architecture with a problem and no shape is the author's choice).
     *
     * @param  list<array<string, mixed>>  $sections  with uuid, type, heading, payload
     * @param  array<string, mixed>|null  $architecture
     * @return list<array<string, mixed>>
     */
    public static function forLesson(?string $summary, array $sections, ?array $architecture): array
    {
        $out = [];
        if (trim((string) ($architecture['problem'] ?? '')) === '' && trim((string) $summary) !== '') {
            $out[] = ['type' => 'problem', 'resolves' => 'no_problem', 'problem' => self::clip(trim((string) $summary), 140), 'draft' => true];
        }
        // Only while the architecture is being started: a written problem with no shape is a decision ("never force it").
        if (($architecture['shape'] ?? null) === null && trim((string) ($architecture['problem'] ?? '')) === '') {
            foreach ($sections as $section) {
                $shape = self::BLOCK_SHAPES[$section['type']] ?? null;
                if ($shape === null) {
                    continue;
                }
                if ($section['type'] === 'flow' && self::loops($section['payload']['edges'] ?? [])) {
                    $shape = 'cycle';
                }
                $out[] = ['type' => 'shape', 'shape' => $shape, 'section' => $section['uuid'], 'block' => $section['type'], 'heading' => $section['heading'] ?? null];
            }
        }

        return $out;
    }

    /**
     * A relation that connects a lesson: from the one before it (same chapter: builds-on; the
     * chapter before: requires), else, with $forward, to the one after it. Null when every
     * candidate would duplicate a pair or close a loop.
     *
     * @param  list<array{slug: string, chapter: int|string}>  $lessons
     * @param  list<array<string, mixed>>  $edges
     * @return array{from: string, to: string, type: string}|null
     */
    private static function relation(string $slug, array $lessons, array $edges, bool $forward): ?array
    {
        $at = array_search($slug, array_column($lessons, 'slug'), true);
        $chapter = $lessons[$at]['chapter'];
        $candidates = [];
        $before = $lessons[$at - 1] ?? null;
        if ($before !== null) {
            $candidates[] = ['from' => $before['slug'], 'to' => $slug, 'type' => $before['chapter'] === $chapter ? 'builds-on' : 'requires'];
        }
        $after = $lessons[$at + 1] ?? null;
        if ($forward && $after !== null) {
            $candidates[] = ['from' => $slug, 'to' => $after['slug'], 'type' => $after['chapter'] === $chapter ? 'builds-on' : 'requires'];
        }
        foreach ($candidates as $edge) {
            if (! self::duplicate($edge, $edges) && ! self::closesLoop($edge, $edges)) {
                return $edge;
            }
        }

        return null;
    }

    /**
     * @param  list<array{slug: string, chapter: int|string}>  $lessons
     * @param  array<string, array{col: int, row: int, lane?: string}>  $placement
     * @param  list<string>  $lanes
     * @return array{col: int, row: int, lane?: string}|null
     */
    private static function placement(string $slug, array $lessons, array $placement, array $lanes): ?array
    {
        $at = array_search($slug, array_column($lessons, 'slug'), true);
        $chapter = $lessons[$at]['chapter'];

        // The lane most of its chapter sits in (the first such lane in course order on a tie).
        $count = [];
        foreach ($lessons as $l) {
            if ($l['chapter'] === $chapter && isset($placement[$l['slug']])) {
                $lane = $placement[$l['slug']]['lane'] ?? '';
                $count[$lane] = ($count[$lane] ?? 0) + 1;
            }
        }
        $lane = $count === [] ? null : array_keys($count, max($count), true)[0];
        if ($lane === null) {
            // Nothing of its chapter is placed yet: follow the nearest placed lesson before it.
            for ($i = $at - 1; $i >= 0 && $lane === null; $i--) {
                $lane = isset($placement[$lessons[$i]['slug']]) ? ($placement[$lessons[$i]['slug']]['lane'] ?? '') : null;
            }
        }
        $lane = $lane !== null && $lane !== '' && in_array($lane, $lanes, true) ? $lane : '';

        $taken = [];
        foreach ($placement as $p) {
            if (($p['lane'] ?? '') === $lane) {
                $taken["{$p['row']}:{$p['col']}"] = true;
            }
        }
        // Start just after the nearest lesson before it in the same lane.
        $start = [0, 0];
        for ($i = $at - 1; $i >= 0; $i--) {
            $p = $placement[$lessons[$i]['slug']] ?? null;
            if ($p !== null && ($p['lane'] ?? '') === $lane) {
                $start = [$p['row'], $p['col'] + 1];
                break;
            }
        }
        for ($row = $start[0]; $row < self::ROWS; $row++) {
            for ($col = $row === $start[0] ? $start[1] : 0; $col < self::COLS; $col++) {
                if (! isset($taken["{$row}:{$col}"])) {
                    return ['col' => $col, 'row' => $row, ...($lane !== '' ? ['lane' => $lane] : [])];
                }
            }
        }

        return null;
    }

    /** @param  list<array<string, mixed>>  $edges */
    private static function connected(string $slug, array $edges): bool
    {
        foreach ($edges as $e) {
            if ($e['from'] === $slug || $e['to'] === $slug) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<array<string, mixed>>  $edges */
    private static function duplicate(array $edge, array $edges): bool
    {
        foreach ($edges as $e) {
            if (($e['from'] === $edge['from'] && $e['to'] === $edge['to']) || ($e['from'] === $edge['to'] && $e['to'] === $edge['from'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an ordering edge would close a loop: its target already leads back to its source.
     *
     * @param  list<array<string, mixed>>  $edges
     */
    public static function closesLoop(array $edge, array $edges): bool
    {
        if (! in_array($edge['type'], self::ORDERING, true)) {
            return false;
        }
        $next = [];
        foreach ($edges as $e) {
            if (in_array($e['type'], self::ORDERING, true)) {
                $next[$e['from']][] = $e['to'];
            }
        }
        $seen = [];
        $stack = [$edge['to']];
        while ($stack !== []) {
            $at = array_pop($stack);
            if ($at === $edge['from']) {
                return true;
            }
            if (isset($seen[$at])) {
                continue;
            }
            $seen[$at] = true;
            array_push($stack, ...($next[$at] ?? []));
        }

        return false;
    }

    /** @param  list<array<string, mixed>>  $edges  a flow block's edges */
    private static function loops(array $edges): bool
    {
        $next = [];
        foreach ($edges as $e) {
            $next[$e['from'] ?? ''][] = $e['to'] ?? '';
        }
        // Depth-first search with colours: reaching a node still on the path is a loop.
        $state = [];
        $visit = function (string $n) use (&$visit, &$state, $next): bool {
            $state[$n] = 1;
            foreach ($next[$n] ?? [] as $m) {
                if (($state[$m] ?? 0) === 1 || (($state[$m] ?? 0) === 0 && $visit($m))) {
                    return true;
                }
            }
            $state[$n] = 2;

            return false;
        };
        foreach (array_keys($next) as $n) {
            if (($state[$n] ?? 0) === 0 && $visit((string) $n)) {
                return true;
            }
        }

        return false;
    }

    private static function clip(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)).'…' : $text;
    }
}
