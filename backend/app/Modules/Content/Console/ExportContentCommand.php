<?php

namespace App\Modules\Content\Console;

use App\Models\Course;
use App\Models\Lab;
use App\Models\Post;
use App\Modules\Content\Services\ContentExporter;
use App\Modules\Labs\Services\LabExporter;
use Illuminate\Console\Command;

/**
 * Writes a course's live (published) content back to its content file, so lessons curated in
 * the admin console can be reviewed and committed (ARCHITECTURE D19). With --post, writes a blog
 * post the same way (F2). Review the diff before committing; `content:import` reads the file back
 * without changing any section UUID.
 */
final class ExportContentCommand extends Command
{
    protected $signature = 'content:export
        {course : Course slug (a post slug with --post)}
        {--post : Export a blog post instead of a course}
        {--lab : Export a lab to labs/<slug>.php instead of a course (D73)}
        {--stdout : Print the file instead of writing it}
        {--path= : Write here instead of database/seeders/content/courses/<slug>.php (posts/<slug>.php, labs/<slug>.php)}';

    protected $description = 'Export a course\'s published lessons, or a blog post, to its content file';

    public function handle(ContentExporter $exporter, LabExporter $labs): int
    {
        $slug = (string) $this->argument('course');

        if ($this->option('lab')) {
            $lab = Lab::query()->where('slug', $slug)->first();
            if (! $lab) {
                $this->error("No lab with slug \"{$slug}\".");

                return self::FAILURE;
            }
            $result = $labs->export($lab);

            return $this->write($result['php'], database_path("seeders/content/labs/{$slug}.php"), $slug, function () use ($result, $slug) {
                $this->info("Exported the lab {$slug}. It holds answers and solutions: review it before committing.");
                if ($result['has_draft']) {
                    $this->warn('The lab has unpublished changes; its live version was exported.');
                }
                $this->warn('If labs.php also defines this slug, remove that entry: the per-lab file wins.');
            });
        }

        if ($this->option('post')) {
            $post = Post::query()->where('slug', $slug)->first();
            if (! $post) {
                $this->error("No post with slug \"{$slug}\".");

                return self::FAILURE;
            }
            $result = $exporter->exportPost($post);

            return $this->write($result['php'], database_path("seeders/content/posts/{$slug}.php"), $slug, function () use ($result, $slug) {
                $this->info("Exported {$result['sections']} sections of the post {$slug}.");
                if ($result['has_draft']) {
                    $this->warn('The post has unpublished changes; its live version was exported.');
                }
            });
        }

        $course = Course::query()->where('slug', $slug)->first();
        if (! $course) {
            $this->error("No course with slug \"{$slug}\".");

            return self::FAILURE;
        }

        $result = $exporter->export($course);

        return $this->write($result['php'], database_path("seeders/content/courses/{$course->slug}.php"), $slug, function () use ($result, $course) {
            $this->info("Exported {$result['lessons']} published lessons of {$course->slug}.");
            if ($result['skipped_drafts']) {
                $this->warn('Unpublished drafts were not exported (their live version was): '.implode(', ', $result['skipped_drafts']));
            }
            if ($result['empty_chapters']) {
                $this->warn('Chapters with no published lessons (kept, empty): '.implode(', ', $result['empty_chapters']));
            }
        });
    }

    private function write(string $php, string $defaultPath, string $slug, callable $report): int
    {
        if ($this->option('stdout')) {
            $this->output->write($php);

            return self::SUCCESS;
        }

        $path = $this->option('path') ?: $defaultPath;
        if (@file_put_contents($path, $php) === false) {
            $flag = $this->option('post') ? ' --post' : ($this->option('lab') ? ' --lab' : '');
            $this->error("Could not write {$path}. In the dev container, run as root: docker compose exec -u root api php artisan content:export {$slug}{$flag}");

            return self::FAILURE;
        }

        $report();
        $this->line("Written to {$path}.");

        return self::SUCCESS;
    }
}
