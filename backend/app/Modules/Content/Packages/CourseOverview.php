<?php

namespace App\Modules\Content\Packages;

use App\Enums\LessonStatus;
use App\Models\Course;
use App\Models\CourseGraph;
use App\Models\Lesson;
use App\Modules\Catalog\Services\RelatedCourses;
use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\Services\ContentImporter;
use App\Modules\Content\Services\LessonWorkingCopy;
use Illuminate\Support\Str;

/**
 * What a chatbot reads before writing content for a course (D71), so it neither repeats nor
 * contradicts what exists: the course, its related courses, every lesson's working copy (the draft
 * when there is one, marked when it differs from what learners see) with each section's key, uuid,
 * type, heading, gist and sources, the block types used, keyword hints for the objectives, the
 * course map and architectures, and the terms already explained. Nothing here knows a block type:
 * gists are the payload's own text. Exercise answer keys live in other tables and are never read.
 */
final class CourseOverview
{
    private const STOPWORDS = ['about', 'after', 'also', 'from', 'have', 'into', 'more', 'only', 'other', 'over', 'same', 'some', 'such', 'than', 'that', 'their', 'them', 'then', 'there', 'these', 'they', 'this', 'what', 'when', 'where', 'which', 'with', 'your', 'each', 'common', 'right', 'using', 'used', 'between', 'explain', 'describe', 'understand', 'list', 'name', 'tell', 'apart', 'happens', 'works', 'work'];

    public function __construct(
        private readonly LessonWorkingCopy $copy,
        private readonly BlockRegistry $blocks,
        private readonly RelatedCourses $related,
    ) {}

    /** @return array<string, mixed> */
    public function build(Course $course): array
    {
        $course->loadMissing(['domain', 'skills', 'prerequisites', 'chapters']);
        $lessons = Lesson::query()
            ->whereIn('course_chapter_id', $course->chapters->modelKeys())
            ->where('status', '!=', LessonStatus::Archived)
            ->with(['chapter', 'draft', 'sections', 'architecture'])
            ->orderBy('position')
            ->get()
            ->groupBy('course_chapter_id');

        $terms = [];
        $chapters = [];
        foreach ($course->chapters->sortBy('position')->values() as $ci => $chapter) {
            $items = [];
            foreach ($lessons[$chapter->id] ?? [] as $lesson) {
                $items[] = $this->lesson($lesson, $terms); // a loop, not map(): $terms collects across lessons by reference
            }
            $chapters[] = ['position' => $ci + 1, 'title' => $chapter->title, 'lessons' => $items];
        }

        $graph = CourseGraph::query()->where('course_id', $course->id)->value('graph');

        return [
            'format' => PackagePlanner::FORMAT,
            'format_version' => PackagePlanner::VERSION,
            'exported_at' => now()->toDateString(),
            'guide' => 'AI_AUTHORING_GUIDE.md',
            'course' => [
                'slug' => $course->slug,
                'title' => $course->title,
                'domain' => $course->domain?->name,
                'difficulty' => $course->difficulty->value,
                'summary' => $course->summary,
                'objectives' => $course->objectives ?? [],
                'skills' => $course->skills->pluck('name')->all(),
                'prerequisites' => $course->prerequisites->map(fn (Course $c) => ['slug' => $c->slug, 'title' => $c->title])->all(),
            ],
            'related_courses' => $this->related->for($course, 5)->map(fn (Course $c) => [
                'slug' => $c->slug,
                'title' => $c->title,
                'summary' => $c->summary,
                'lessons' => $c->lessons()->visibleToLearners()->orderBy('course_chapters.position')->orderBy('lessons.position')->pluck('lessons.title')->all(),
            ])->values()->all(),
            'map' => $graph === null ? null : [
                'problem' => $graph['problem'] ?? null,
                'relations' => array_map(fn ($e) => ['from' => $e['from'], 'type' => $e['type'], 'to' => $e['to']], $graph['edges'] ?? []),
            ],
            'chapters' => $chapters,
            'terms' => array_values(array_map(fn ($t) => ['term' => $t['term'], 'where' => array_slice(array_values(array_unique($t['where'])), 0, 5)], $terms)),
            'block_types' => array_keys($this->blocks->all()),
        ];
    }

    /** @param  array<string, array{term: string, where: list<string>}>  $terms */
    private function lesson(Lesson $lesson, array &$terms): array
    {
        $current = $this->copy->current($lesson);
        $live = $this->copy->fromLive($lesson);
        $keys = ContentImporter::sectionKeys($current['sections']);
        $keyOf = array_combine(array_column($current['sections'], 'uuid'), $keys);

        $sections = [];
        foreach ($current['sections'] as $i => $s) {
            $gist = self::gist($s['payload']);
            $sections[] = [
                'key' => $keys[$i],
                'uuid' => $s['uuid'],
                'type' => $s['type'],
                'heading' => $s['heading'],
                'gist' => $gist,
                'states_facts' => $this->blocks->statesFacts($s['type'], $s['payload']),
                'sources' => array_map(fn ($src) => $src['label'], $s['sources'] ?? []),
            ];
            foreach (self::boldTerms($s['payload']) as $term) {
                $id = preg_replace('/(?<=[a-z]{3})s$/', '', mb_strtolower($term)); // "Hosts" and "host" are one term
                $terms[$id]['term'] ??= $term;
                $terms[$id]['where'][] = "{$lesson->slug}#{$keys[$i]}";
            }
        }

        $used = array_values(array_unique(array_column($sections, 'type')));
        $architecture = $current['architecture'] ?? null;

        return [
            'slug' => $lesson->slug,
            'title' => $current['title'],
            'status' => $lesson->status->value,
            // Learners have not seen this version: a never-published lesson, or a draft that differs from the live one.
            'unpublished_draft' => $lesson->status === LessonStatus::Draft || ($lesson->draft !== null && self::differs($current, $live)),
            'minutes' => $current['estimated_minutes'],
            'summary' => $current['summary'],
            'objectives' => array_map(fn (string $o) => ['objective' => $o, 'hint' => self::hint($o, $current['title'], $this->teaching($sections))], $current['objectives']),
            'takeaways' => $current['key_takeaways'],
            'fingerprint' => Fingerprint::of($current['sections']),
            'sections' => $sections,
            'blocks_used' => $used,
            'blocks_unused' => array_values(array_diff(array_keys($this->blocks->all()), $used)),
            'architecture' => $architecture === null ? null : [
                'shape' => $architecture['shape'] ?? null,
                'problem' => $architecture['problem'] ?? null,
                'nodes' => array_map(fn ($n) => [
                    'id' => $n['id'],
                    'label' => $n['label'],
                    'section_key' => isset($n['section']) ? ($keyOf[$n['section']] ?? null) : null,
                    'section_uuid' => $n['section'] ?? null,
                    'lesson' => $n['lesson'] ?? null,
                ], $architecture['nodes'] ?? []),
            ],
        ];
    }

    /** The overview as Markdown, for a chatbot to read next to the AI authoring guide. */
    public static function markdown(array $o): string
    {
        $c = $o['course'];
        $md = ["# Course overview: {$c['title']}", ''];
        $md[] = "> Format `{$o['format']}`, version {$o['format_version']}. Exported {$o['exported_at']}. Read it with the AI authoring guide (`{$o['guide']}`, \"Download AI guide\" in the admin console).";
        $md[] = '> It describes each lesson as it is being edited (its draft when there is one). Target an existing section by its **uuid**; give a **key** only to a new section.';
        $md[] = '';
        $md[] = '## The course';
        $md[] = '';
        $md[] = "- **Slug:** `{$c['slug']}`";
        $md[] = "- **Domain:** {$c['domain']} · **Difficulty:** {$c['difficulty']} of 3";
        $md[] = "- **Summary:** {$c['summary']}";
        $md[] = '- **Skills:** '.($c['skills'] ? implode(', ', $c['skills']) : 'none');
        $md[] = '- **Prerequisites:** '.($c['prerequisites'] ? implode(', ', array_map(fn ($p) => "`{$p['slug']}` ({$p['title']})", $c['prerequisites'])) : 'none');
        $md[] = '- **Objectives:**';
        foreach ($c['objectives'] as $objective) {
            $md[] = "  - {$objective}";
        }
        $md[] = '';
        $md[] = '## Related courses';
        $md[] = '';
        $md[] = 'These cover nearby ground. Link to them instead of teaching what they teach again.';
        $md[] = '';
        foreach ($o['related_courses'] as $r) {
            $md[] = "- `{$r['slug']}` **{$r['title']}**: {$r['summary']}";
            $md[] = '  - Lessons: '.($r['lessons'] ? implode('; ', $r['lessons']) : 'none yet');
        }
        if ($o['related_courses'] === []) {
            $md[] = '- None.';
        }

        if ($o['map'] !== null) {
            $md[] = '';
            $md[] = '## Course map';
            $md[] = '';
            $md[] = '- **The course\'s problem:** '.($o['map']['problem'] ?? 'not written yet');
            foreach ($o['map']['relations'] as $r) {
                $md[] = "- `{$r['from']}` {$r['type']} `{$r['to']}`";
            }
        }

        $md[] = '';
        $md[] = '## Lessons';
        foreach ($o['chapters'] as $chapter) {
            $md[] = '';
            $md[] = "### Chapter {$chapter['position']}: {$chapter['title']}";
            foreach ($chapter['lessons'] as $li => $l) {
                $state = $l['unpublished_draft'] ? '**draft – not yet published** (learners have not seen this version)' : $l['status'];
                $md[] = '';
                $md[] = '#### '.($li + 1).". {$l['title']} (`{$l['slug']}`)";
                $md[] = '';
                $md[] = "- **State:** {$state} · **Minutes:** {$l['minutes']} · **Fingerprint:** `{$l['fingerprint']}` (send it back as `base.lesson_fingerprint`)";
                $md[] = "- **Summary:** {$l['summary']}";
                $md[] = '- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):';
                foreach ($l['objectives'] as $i => $ob) {
                    $hint = $ob['hint'] ? implode(', ', array_map(fn ($k) => "`{$k}`", $ob['hint'])) : '**no dedicated section found**';
                    $md[] = '  '.($i + 1).". {$ob['objective']} → {$hint}";
                }
                if ($l['takeaways']) {
                    $md[] = '- **Takeaways:** '.implode(' · ', $l['takeaways']);
                }
                $md[] = '- **Sections:**';
                $md[] = '';
                $md[] = '| # | key | uuid | type | heading | gist | sources |';
                $md[] = '|---|---|---|---|---|---|---|';
                foreach ($l['sections'] as $i => $s) {
                    $md[] = '| '.implode(' | ', [
                        $i + 1, "`{$s['key']}`", "`{$s['uuid']}`", $s['type'],
                        self::cell($s['heading'] ?? '–'), self::cell($s['gist']),
                        $s['sources'] ? self::cell(implode('; ', $s['sources'])) : 'none',
                    ]).' |';
                }
                $md[] = '';
                $md[] = '- **Block types used:** '.implode(', ', $l['blocks_used']).'. **Not used:** '.implode(', ', $l['blocks_unused']).'.';
                if ($l['architecture'] !== null) {
                    $a = $l['architecture'];
                    $md[] = '- **Architecture on the course map** ('.($a['shape'] ?? 'no shape').'): '.($a['problem'] ?? '');
                    foreach ($a['nodes'] as $n) {
                        $points = $n['section_uuid'] ? " → section `{$n['section_key']}` (`{$n['section_uuid']}`)" : '';
                        $md[] = "  - `{$n['id']}` {$n['label']}{$points}".($n['lesson'] ? " → lesson `{$n['lesson']}`" : '');
                    }
                }
            }
        }

        $md[] = '';
        $md[] = '## Terms already explained';
        $md[] = '';
        $md[] = 'Terms the lessons introduce in bold, and where. Refer to these instead of explaining them again.';
        $md[] = '';
        foreach ($o['terms'] as $t) {
            $md[] = "- **{$t['term']}**: ".implode(', ', array_map(fn ($w) => "`{$w}`", $t['where']));
        }
        $md[] = '';
        $md[] = '## Block types available';
        $md[] = '';
        $md[] = implode(', ', $o['block_types']).'. Their contracts and examples are in the AI authoring guide.';

        return implode("\n", $md)."\n";
    }

    private static function cell(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $text);
    }

    /** Sections that teach (they state facts, D69); a knowledge check practises, it does not serve an objective. */
    private function teaching(array $sections): array
    {
        return array_values(array_filter($sections, fn ($s) => $s['states_facts']));
    }

    private static function differs(array $current, array $live): bool
    {
        $fields = ['title', 'summary', 'estimated_minutes', 'objectives', 'key_takeaways', 'resources'];

        return Fingerprint::of($current['sections']) !== Fingerprint::of($live['sections'])
            || array_intersect_key($current, array_flip($fields)) != array_intersect_key($live, array_flip($fields));
    }

    /** The payload's own text in order, without Markdown, cut to about 200 characters. */
    public static function gist(array $payload): string
    {
        $texts = [];
        array_walk_recursive($payload, function ($value, $key) use (&$texts) {
            // Identifiers and settings are not content; everything else that is text is.
            if (is_string($value) && ! in_array($key, ['id', 'tone', 'language', 'kind', 'media', 'url', 'key', 'shape', 'aspect', 'from', 'to', 'parent'], true)) {
                $texts[] = $value;
            }
        });
        $plain = preg_replace(['/\*\*|__|`/', '/\[([^\]]+)\]\([^)]+\)/', '/\s+/'], ['', '$1', ' '], implode(' · ', $texts));

        return Str::limit(trim((string) $plain), 200);
    }

    /** @return list<string> terms written in bold, which the lessons use to introduce a term */
    private static function boldTerms(array $payload): array
    {
        $found = [];
        array_walk_recursive($payload, function ($value) use (&$found) {
            if (is_string($value) && preg_match_all('/\*\*([^*]{2,60})\*\*/', $value, $m)) {
                array_push($found, ...array_map('trim', $m[1]));
            }
        });

        return array_values(array_unique($found));
    }

    /**
     * Teaching sections whose heading or opening text share the objective's distinctive words: a hint
     * for the author, not a judgement. A dedicated section names its subject early, so the gist (the
     * first 200 characters) is what is matched. Words of the lesson title are left out.
     *
     * @return list<string> section keys
     */
    private static function hint(string $objective, string $title, array $sections): array
    {
        $common = self::words($title);
        $wanted = array_values(array_diff(self::words($objective), $common));
        if ($wanted === []) {
            return [];
        }
        $needed = count($wanted) >= 3 ? 2 : 1;
        $keys = [];
        foreach ($sections as $s) {
            $have = self::words(($s['heading'] ?? '').' '.$s['gist']);
            if (count(array_intersect($wanted, $have)) >= $needed) {
                $keys[] = $s['key'];
            }
        }

        return $keys;
    }

    /** @return list<string> distinct lower-case words of four letters or more, a plural "s" removed */
    private static function words(string $text): array
    {
        preg_match_all('/[a-z][a-z0-9-]{3,}/', mb_strtolower($text), $m);
        $words = array_map(fn ($w) => preg_replace('/(?<=[a-z]{3})s$/', '', $w), $m[0]);

        return array_values(array_unique(array_diff($words, self::STOPWORDS)));
    }
}
