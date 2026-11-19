<?php

namespace Database\Seeders;

use App\Models\Career;
use App\Models\Course;
use App\Models\Domain;
use App\Models\Lesson;
use App\Models\Skill;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads learning content from database/seeders/content/*.php.
 *
 * Content is upserted by slug, so re-running the seeder updates text without
 * deleting lessons and therefore without touching learners' progress. Lesson
 * sections are replaced wholesale (nothing references them). The data is
 * validated first; a broken reference stops the seed before anything is written.
 */
class ContentSeeder extends Seeder
{
    /** Diagram components the frontend can render (frontend/src/features/learning/diagrams). */
    private const DIAGRAMS = ['osi-model', 'tcp-handshake', 'cia-triad'];

    private const CALLOUT_TONES = ['info', 'tip', 'warning'];

    private string $dir;

    public function run(): void
    {
        $this->dir = database_path('seeders/content');

        $domains = require "{$this->dir}/domains.php";
        $courses = array_map(fn ($file) => require $file, glob("{$this->dir}/courses/*.php"));
        $topics = require "{$this->dir}/topics.php";
        $careers = require "{$this->dir}/careers.php";

        $this->validate($domains, $courses, $topics, $careers);

        DB::transaction(function () use ($domains, $courses, $topics, $careers) {
            $this->seedDomains($domains);
            $this->seedCourses($courses);
            $this->seedTopics($topics);
            $this->seedCareers($careers);
        });

        $this->command?->info(sprintf(
            'Content: %d domains, %d courses, %d lessons, %d topics, %d careers.',
            count($domains), count($courses), Lesson::query()->count(), count($topics), count($careers),
        ));
    }

    /** @param  array<string, array<string, mixed>>  $domains */
    private function seedDomains(array $domains): void
    {
        $position = 0;
        foreach ($domains as $slug => $data) {
            $domain = Domain::query()->updateOrCreate(['slug' => $slug], [
                'name' => $data['name'],
                'description' => $data['description'],
                'position' => $position++,
            ]);
            foreach ($data['skills'] as $skillSlug => $name) {
                Skill::query()->updateOrCreate(['slug' => $skillSlug], ['domain_id' => $domain->id, 'name' => $name]);
            }
        }
    }

    /** @param  list<array<string, mixed>>  $courses */
    private function seedCourses(array $courses): void
    {
        $domainIds = Domain::query()->pluck('id', 'slug');
        $skillIds = Skill::query()->pluck('id', 'slug');

        foreach ($courses as $data) {
            $course = Course::query()->updateOrCreate(['slug' => $data['slug']], [
                'domain_id' => $domainIds[$data['domain']],
                'title' => $data['title'],
                'summary' => $data['summary'],
                'description' => $data['description'],
                'difficulty' => $data['difficulty'],
                'objectives' => $data['objectives'],
                'is_published' => true,
                'published_at' => $data['published_at'],
            ]);
            $course->skills()->sync($skillIds->only($data['skills'])->values());

            foreach ($data['chapters'] as $chapterIndex => $chapterData) {
                $chapter = $course->chapters()->updateOrCreate(
                    ['position' => $chapterIndex + 1],
                    ['title' => $chapterData['title']],
                );

                foreach ($chapterData['lessons'] as $lessonIndex => $l) {
                    $lesson = Lesson::query()->updateOrCreate(['slug' => $l['slug']], [
                        'course_chapter_id' => $chapter->id,
                        'position' => $lessonIndex + 1,
                        'title' => $l['title'],
                        'summary' => $l['summary'],
                        'objectives' => $l['objectives'],
                        'key_takeaways' => $l['takeaways'],
                        'resources' => array_map(fn ($r) => ['label' => $r[0], 'url' => $r[1]], $l['resources']),
                        'estimated_minutes' => $l['minutes'],
                    ]);

                    $lesson->sections()->delete();
                    foreach ($l['sections'] as $sectionIndex => $section) {
                        $lesson->sections()->create(['position' => $sectionIndex + 1, ...$this->section($section)]);
                    }
                }
            }
        }

        // Second pass: prerequisites may point at courses defined in later files.
        $courseIds = Course::query()->pluck('id', 'slug');
        foreach ($courses as $data) {
            Course::query()->find($courseIds[$data['slug']])
                ->prerequisites()->sync($courseIds->only($data['prerequisites'])->values());
        }
    }

    /**
     * Compact content format => stored section.
     * [type, heading, body, extra, data] where extra is the callout tone, code language or diagram
     * caption, and data (diagrams only) is the content the diagram component renders.
     *
     * @param  array<int, mixed>  $s
     * @return array{type: string, heading: ?string, payload: array<string, mixed>}
     */
    private function section(array $s): array
    {
        [$type, $heading, $body] = $s;
        $extra = $s[3] ?? null;

        $payload = match ($type) {
            'prose' => ['text' => trim($body)],
            'callout' => ['text' => trim($body), 'tone' => $extra ?? 'info'],
            'steps' => ['items' => array_values($body)],
            'code' => ['code' => rtrim($body), 'language' => $extra ?? 'text'],
            'diagram' => ['key' => $body, 'caption' => $extra, 'data' => $s[4] ?? []],
        };

        return ['type' => $type, 'heading' => $heading, 'payload' => $payload];
    }

    /** @param  array<string, array<string, mixed>>  $topics */
    private function seedTopics(array $topics): void
    {
        $domainIds = Domain::query()->pluck('id', 'slug');
        $lessonIds = Lesson::query()->pluck('id', 'slug');

        foreach ($topics as $slug => $data) {
            $topic = Topic::query()->updateOrCreate(['slug' => $slug], [
                'domain_id' => $domainIds[$data['domain']],
                'title' => $data['title'],
                'summary' => $data['summary'],
                'level' => $data['level'],
            ]);
            $topic->lessons()->sync(collect($data['lessons'])->mapWithKeys(fn ($lessonSlug, $i) => [
                $lessonIds[$lessonSlug] => ['position' => $i + 1, 'is_required' => true],
            ]));
        }

        $topicIds = Topic::query()->pluck('id', 'slug');
        foreach ($topics as $slug => $data) {
            Topic::query()->find($topicIds[$slug])->prerequisites()->sync($topicIds->only($data['requires'])->values());
        }
    }

    /** @param  array<string, array<string, mixed>>  $careers */
    private function seedCareers(array $careers): void
    {
        $domainIds = Domain::query()->pluck('id', 'slug');
        $skillIds = Skill::query()->pluck('id', 'slug');
        $topicIds = Topic::query()->pluck('id', 'slug');

        $position = 0;
        foreach ($careers as $slug => $data) {
            $career = Career::query()->updateOrCreate(['slug' => $slug], [
                'title' => $data['title'],
                'summary' => $data['summary'],
                'description' => $data['description'],
                'responsibilities' => $data['responsibilities'],
                'is_published' => true,
                'position' => $position++,
            ]);
            $career->domains()->sync(collect($data['domains'])->mapWithKeys(
                fn ($level, $domain) => [$domainIds[$domain] => ['required_level' => $level]],
            ));
            $career->skills()->sync($skillIds->only($data['skills'])->values());

            $topicsWithPivot = [];
            foreach ($data['stages'] as $stageIndex => $stageTopics) {
                foreach ($stageTopics as $i => $topicSlug) {
                    $topicsWithPivot[$topicIds[$topicSlug]] = ['stage' => $stageIndex + 1, 'position' => $i + 1];
                }
            }
            $career->topics()->sync($topicsWithPivot);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $domains
     * @param  list<array<string, mixed>>  $courses
     * @param  array<string, array<string, mixed>>  $topics
     * @param  array<string, array<string, mixed>>  $careers
     */
    private function validate(array $domains, array $courses, array $topics, array $careers): void
    {
        $errors = [];
        $skills = array_merge(...array_map(fn ($d) => array_keys($d['skills']), array_values($domains)));
        $courseSlugs = array_column($courses, 'slug');
        $lessonSlugs = [];

        foreach ($courses as $course) {
            $where = "course {$course['slug']}";
            isset($domains[$course['domain']]) || $errors[] = "{$where}: unknown domain {$course['domain']}";
            in_array($course['difficulty'], [1, 2, 3], true) || $errors[] = "{$where}: difficulty must be 1-3";
            foreach (array_diff($course['skills'], $skills) as $s) {
                $errors[] = "{$where}: unknown skill {$s}";
            }
            foreach (array_diff($course['prerequisites'], $courseSlugs) as $p) {
                $errors[] = "{$where}: unknown prerequisite course {$p}";
            }
            foreach ($course['chapters'] as $chapter) {
                foreach ($chapter['lessons'] as $lesson) {
                    in_array($lesson['slug'], $lessonSlugs, true) && $errors[] = "duplicate lesson slug {$lesson['slug']}";
                    $lessonSlugs[] = $lesson['slug'];
                    foreach ($lesson['sections'] as $i => $s) {
                        $at = "lesson {$lesson['slug']} section ".($i + 1);
                        match ($s[0]) {
                            'prose', 'steps', 'code' => null,
                            'callout' => in_array($s[3] ?? 'info', self::CALLOUT_TONES, true) || $errors[] = "{$at}: unknown callout tone",
                            'diagram' => in_array($s[2], self::DIAGRAMS, true) && ! empty($s[4])
                                || $errors[] = "{$at}: diagram {$s[2]} is unknown or has no data",
                            default => $errors[] = "{$at}: unknown section type {$s[0]}",
                        };
                    }
                }
            }
        }

        foreach ($topics as $slug => $topic) {
            isset($domains[$topic['domain']]) || $errors[] = "topic {$slug}: unknown domain";
            foreach (array_diff($topic['lessons'], $lessonSlugs) as $l) {
                $errors[] = "topic {$slug}: unknown lesson {$l}";
            }
            foreach (array_diff($topic['requires'], array_keys($topics)) as $r) {
                $errors[] = "topic {$slug}: unknown prerequisite {$r}";
            }
        }
        if ($cycle = $this->findCycle($topics)) {
            $errors[] = 'topic prerequisites form a cycle: '.implode(' -> ', $cycle);
        }

        foreach ($careers as $slug => $career) {
            foreach (array_keys($career['domains']) as $d) {
                isset($domains[$d]) || $errors[] = "career {$slug}: unknown domain {$d}";
            }
            foreach (array_diff($career['skills'], $skills) as $s) {
                $errors[] = "career {$slug}: unknown skill {$s}";
            }
            // A topic's prerequisites must be in an earlier stage of the same career.
            $stageOf = [];
            foreach ($career['stages'] as $i => $stage) {
                foreach ($stage as $t) {
                    isset($topics[$t]) ? $stageOf[$t] = $i : $errors[] = "career {$slug}: unknown topic {$t}";
                }
            }
            foreach ($stageOf as $t => $stage) {
                foreach ($topics[$t]['requires'] as $req) {
                    if (! isset($stageOf[$req]) || $stageOf[$req] >= $stage) {
                        $errors[] = "career {$slug}: topic {$t} needs {$req} in an earlier stage";
                    }
                }
            }
        }

        if ($errors) {
            throw new InvalidArgumentException("Content is invalid:\n- ".implode("\n- ", $errors));
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $topics
     * @return list<string>|null
     */
    private function findCycle(array $topics): ?array
    {
        $state = [];
        $path = [];
        $visit = function (string $t) use (&$visit, &$state, &$path, $topics): ?array {
            if (($state[$t] ?? null) === 'done') {
                return null;
            }
            if (($state[$t] ?? null) === 'active') {
                return [...array_slice($path, array_search($t, $path, true)), $t];
            }
            $state[$t] = 'active';
            $path[] = $t;
            foreach ($topics[$t]['requires'] ?? [] as $req) {
                if (isset($topics[$req]) && ($cycle = $visit($req))) {
                    return $cycle;
                }
            }
            array_pop($path);
            $state[$t] = 'done';

            return null;
        };

        foreach (array_keys($topics) as $t) {
            if ($cycle = $visit($t)) {
                return $cycle;
            }
        }

        return null;
    }
}
