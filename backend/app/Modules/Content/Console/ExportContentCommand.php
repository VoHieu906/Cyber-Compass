<?php

namespace App\Modules\Content\Console;

use App\Models\Course;
use App\Modules\Content\Services\ContentExporter;
use Illuminate\Console\Command;

/**
 * Writes a course's live (published) content back to its content file, so lessons curated in
 * the admin console can be reviewed and committed (ARCHITECTURE D19). Review the diff before
 * committing; `content:import` reads the file back without changing any section UUID.
 */
final class ExportContentCommand extends Command
{
    protected $signature = 'content:export
        {course : Course slug}
        {--stdout : Print the file instead of writing it}
        {--path= : Write here instead of database/seeders/content/courses/<slug>.php}';

    protected $description = 'Export a course\'s published lessons to its content file';

    public function handle(ContentExporter $exporter): int
    {
        $course = Course::query()->where('slug', $this->argument('course'))->first();
        if (! $course) {
            $this->error("No course with slug \"{$this->argument('course')}\".");

            return self::FAILURE;
        }

        $result = $exporter->export($course);

        if ($this->option('stdout')) {
            $this->output->write($result['php']);

            return self::SUCCESS;
        }

        $path = $this->option('path') ?: database_path("seeders/content/courses/{$course->slug}.php");
        if (@file_put_contents($path, $result['php']) === false) {
            $this->error("Could not write {$path}. In the dev container, run as root: docker compose exec -u root api php artisan content:export {$course->slug}");

            return self::FAILURE;
        }

        $this->info("Exported {$result['lessons']} published lessons of {$course->slug} to {$path}.");
        if ($result['skipped_drafts']) {
            $this->warn('Unpublished drafts were not exported (their live version was): '.implode(', ', $result['skipped_drafts']));
        }
        if ($result['empty_chapters']) {
            $this->warn('Chapters with no published lessons (kept, empty): '.implode(', ', $result['empty_chapters']));
        }

        return self::SUCCESS;
    }
}
