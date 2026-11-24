<?php

namespace App\Modules\Content\Console;

use App\Models\Course;
use App\Models\CourseGraph;
use App\Models\LessonArchitecture;
use App\Modules\Content\CourseMap\CourseMap;
use App\Modules\Content\CourseMap\CourseMapSuggestions;
use App\Modules\Content\CourseMap\MapSchemas;
use App\Modules\Content\CourseMap\MapVersions;
use Illuminate\Console\Command;

/**
 * The course map health check (F3): validates every stored course map and lesson architecture
 * (schema, budgets, references, section UUIDs) and lists what authors should look at (unplaced,
 * unconnected and dangling lessons, links leaving the course). Errors exit non-zero; issues are
 * warnings. `cybercompass:release` runs it with --warn-only after the import (D60); a Pest test
 * runs it on the seed content, so CI fails on a broken content file.
 *
 *   php artisan content:check-graphs [--warn-only] [--upgrade]
 */
final class CheckGraphsCommand extends Command
{
    protected $signature = 'content:check-graphs
        {--warn-only : Report errors but always exit 0 (the release task)}
        {--upgrade : Rewrite stored maps and architectures at the current schema version}';

    protected $description = 'Validate every course map and lesson architecture';

    public function handle(CourseMap $map, CourseMapSuggestions $suggestions): int
    {
        $errors = 0;
        $warnings = 0;
        $upgraded = 0;

        foreach (LessonArchitecture::query()->with('lesson.sections:id,lesson_id,uuid')->get() as $row) {
            $lesson = $row->lesson;
            [$found] = MapSchemas::architecture($row->architecture, $lesson->sections->pluck('uuid')->all(), "lessons.{$lesson->slug}.architecture");
            $errors += $this->report($found);
            if (! $found && $this->option('upgrade') && ($row->architecture['version'] ?? null) !== MapVersions::CURRENT) {
                $row->update(['architecture' => MapVersions::upgrade($row->architecture, 'architecture')[1]]);
                $upgraded++;
            }
        }

        foreach (CourseGraph::query()->with('course')->get() as $row) {
            /** @var Course $course */
            $course = $row->course;
            [$found] = MapSchemas::courseGraph($row->graph, null, "courses.{$course->slug}.graph");
            $errors += $this->report($found);
            if ($found) {
                continue;
            }
            foreach ($map->issues($course, $row->graph) as $issue) {
                $this->line("  <fg=yellow>warn</>  {$course->slug}: {$issue['type']} {$issue['lesson']}".(isset($issue['detail']) ? " ({$issue['detail']})" : ''));
                $warnings++;
            }
            // Information only (F4 Part C, D63): what the admin panel would offer for those warnings.
            foreach ($suggestions->forCourse($course, $row->graph) as $s) {
                if (in_array($s['type'], ['relation', 'placement'], true)) {
                    $what = $s['type'] === 'relation' ? "{$s['edge']['type']} from {$s['edge']['from']} to {$s['edge']['to']}" : 'column '.($s['placement']['col'] + 1).', row '.($s['placement']['row'] + 1).(isset($s['placement']['lane']) ? " in {$s['placement']['lane']}" : '');
                    $this->line("  <fg=cyan>info</>  {$course->slug}: suggest {$s['type']} {$s['lesson']}: {$what}");
                }
            }
            if ($this->option('upgrade') && ($row->graph['version'] ?? null) !== MapVersions::CURRENT) {
                $row->update(['graph' => MapVersions::upgrade($row->graph, 'graph')[1]]);
                $upgraded++;
            }
        }

        $this->info("Course maps: {$errors} error(s), {$warnings} warning(s)".($this->option('upgrade') ? ", {$upgraded} upgraded" : '').'.');

        return $errors > 0 && ! $this->option('warn-only') ? self::FAILURE : self::SUCCESS;
    }

    /** @param  array<string, list<string>>  $found */
    private function report(array $found): int
    {
        foreach ($found as $path => $messages) {
            $this->line("  <fg=red>error</> {$path}: ".implode(' ', $messages));
        }

        return count($found);
    }
}
