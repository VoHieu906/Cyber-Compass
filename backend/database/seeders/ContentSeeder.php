<?php

namespace Database\Seeders;

use App\Models\Career;
use App\Models\Domain;
use App\Models\Lesson;
use App\Models\Skill;
use App\Models\Topic;
use App\Modules\Content\Services\ContentImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads learning content and blog posts from database/seeders/content/*.php (posts in posts/*.php).
 *
 * Courses, chapters, lessons and sections go through the Content module's ContentImporter,
 * which validates every block with the BlockRegistry (the same rules as the admin console)
 * and never overwrites lessons edited in the admin unless $force is set (ARCHITECTURE D19).
 * Domains, topics and careers are seeded here. Everything is validated before anything is
 * written; a broken reference stops the seed.
 */
class ContentSeeder extends Seeder
{
    /** Set by `php artisan content:import --force` to overwrite admin-edited lessons. */
    public bool $force = false;

    public function run(ContentImporter $importer): void
    {
        $dir = database_path('seeders/content');

        $domains = require "{$dir}/domains.php";
        $courses = array_map(fn ($file) => require $file, glob("{$dir}/courses/*.php"));
        $topics = require "{$dir}/topics.php";
        $careers = require "{$dir}/careers.php";
        $posts = array_map(fn ($file) => require $file, glob("{$dir}/posts/*.php"));

        $this->validate($domains, ContentImporter::lessonSlugs($courses), $topics, $careers);

        [$report, $postReport] = DB::transaction(function () use ($domains, $courses, $topics, $careers, $posts, $importer) {
            $this->seedDomains($domains);
            $report = $importer->import($courses, $this->force);
            $this->seedTopics($topics);
            $this->seedCareers($careers);

            return [$report, $importer->importPosts($posts, $this->force)];
        });

        $this->command?->info(sprintf(
            'Content: %d domains, %d courses (%d lessons written), %d topics, %d careers, %d posts (%d written).',
            count($domains), $report['courses'], $report['lessons_written'], count($topics), count($careers), $postReport['posts'], $postReport['posts_written'],
        ));
        if ($skipped = [...$report['skipped'], ...$postReport['skipped']]) {
            $this->command?->warn('Skipped (edited in the admin; use content:import --force to overwrite): '.implode(', ', $skipped));
        }
        if ($archived = [...$report['archived'], ...$postReport['archived']]) {
            $this->command?->warn('Archived (no longer in the content files): '.implode(', ', $archived));
        }
        if ($report['graphs_skipped']) {
            $this->command?->warn('Course maps skipped (saved in the admin; use content:import --force to overwrite): '.implode(', ', $report['graphs_skipped']));
        }
        foreach ($report['warnings'] as $warning) {
            $this->command?->warn("Course map: {$warning}");
        }
        // Exercises point at the sections just written (D29).
        $this->call(ExerciseSeeder::class);
        $this->call(LabSeeder::class);
        // The question bank is content too; its domains must exist first.
        $this->call(AssessmentSeeder::class);
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

    /**
     * Topic links are synced for seed-owned lessons only: links to lessons created or
     * edited in the admin are left alone.
     *
     * @param  array<string, array<string, mixed>>  $topics
     */
    private function seedTopics(array $topics): void
    {
        $domainIds = Domain::query()->pluck('id', 'slug');
        $lessons = Lesson::query()->get(['id', 'slug', 'created_by', 'updated_by'])->keyBy('slug');
        $seedOwned = $lessons->filter(fn (Lesson $l) => $l->created_by === null && $l->updated_by === null)->pluck('id');

        foreach ($topics as $slug => $data) {
            $topic = Topic::query()->updateOrCreate(['slug' => $slug], [
                'domain_id' => $domainIds[$data['domain']],
                'title' => $data['title'],
                'summary' => $data['summary'],
                'level' => $data['level'],
            ]);

            $wanted = collect($data['lessons'])->mapWithKeys(fn ($lessonSlug, $i) => [
                $lessons[$lessonSlug]->id => ['position' => $i + 1, 'is_required' => true],
            ]);
            $topic->lessons()->syncWithoutDetaching($wanted);
            $topic->lessons()->detach($seedOwned->diff($wanted->keys())->intersect($topic->lessons()->pluck('lessons.id')));
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
     * Domain, topic and career references. Course and section rules live in ContentImporter
     * and the BlockRegistry.
     *
     * @param  array<string, array<string, mixed>>  $domains
     * @param  list<string>  $lessonSlugs
     * @param  array<string, array<string, mixed>>  $topics
     * @param  array<string, array<string, mixed>>  $careers
     */
    private function validate(array $domains, array $lessonSlugs, array $topics, array $careers): void
    {
        $errors = [];
        $skills = array_merge(...array_map(fn ($d) => array_keys($d['skills']), array_values($domains)));

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
