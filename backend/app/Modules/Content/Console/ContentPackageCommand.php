<?php

namespace App\Modules\Content\Console;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\User;
use App\Modules\Content\Packages\CourseOverview;
use App\Modules\Content\Packages\ImportPackage;
use App\Modules\Content\Packages\PackageExporter;
use App\Modules\Content\Packages\PackagePlan;
use App\Modules\Content\Packages\PackagePlanner;
use App\Modules\Content\Packages\PackageReader;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Content packages from the command line (F6, D67), through the same services as the admin:
 *
 *   content:package validate <file>                    errors, warnings and the section changes; writes nothing
 *   content:package import <file> --as=<admin email>   writes the drafts (never publishes)
 *   content:package export --lesson=<slug> [--sections=a,b] [--out=<file>]
 *   content:package export --post=<slug> [--sections=a,b] [--out=<file>]
 *   content:package overview --course=<slug> [--json] [--out=<file>]   what a chatbot reads first (D71)
 */
final class ContentPackageCommand extends Command
{
    protected $signature = 'content:package
        {action : validate, import, export or overview}
        {file? : The .ccpkg.json or .ccpkg.zip to validate or import}
        {--as= : Email of the admin the import is recorded for}
        {--lesson= : Lesson slug to export}
        {--post= : Post slug to export}
        {--sections= : Export only these section keys (comma separated), as replace operations}
        {--course= : Course slug for the overview}
        {--json : The overview as JSON instead of Markdown}
        {--out= : Write the export here instead of printing it}';

    protected $description = 'Validate, import or export a content package (lessons, posts, blocks)';

    public function handle(PackageReader $reader, PackagePlanner $planner, ImportPackage $import, PackageExporter $exporter): int
    {
        return match ($this->argument('action')) {
            'validate', 'import' => $this->readAnd($reader, fn (array $package) => $this->argument('action') === 'validate'
                ? $this->report($planner->plan($package['envelope'], $package['root']))
                : $this->import($import, $package)),
            'export' => $this->export($exporter),
            'overview' => $this->overview(),
            default => $this->refuse('The action is validate, import, export or overview.'),
        };
    }

    private function readAnd(PackageReader $reader, callable $then): int
    {
        $file = (string) $this->argument('file');
        if ($file === '' || ! is_file($file)) {
            return $this->refuse('Give the path of a package file.');
        }
        try {
            $package = $reader->read($file, basename($file));
        } catch (ValidationException $e) {
            return $this->errors($e->errors());
        }
        try {
            return $then($package);
        } finally {
            PackageReader::cleanup($package['root']);
        }
    }

    private function import(ImportPackage $import, array $package): int
    {
        $admin = User::query()->where('email', (string) $this->option('as'))->first();
        if ($admin?->role !== UserRole::Admin) {
            return $this->refuse('--as must name an admin account.');
        }
        try {
            $plan = $import->handle($admin, $package['envelope'], $package['root']);
        } catch (ValidationException $e) {
            return $this->errors($e->errors());
        } catch (ConflictHttpException $e) {
            return $this->refuse($e->getMessage());
        }
        $this->report($plan);
        $this->info('Imported into drafts. Review and publish them in the admin console.');

        return self::SUCCESS;
    }

    private function report(PackagePlan $plan): int
    {
        foreach ($plan->documents as $doc) {
            $this->line(sprintf('%s %s%s', $doc['type'], $doc['slug'], $doc['is_new'] ? ' (new draft)' : ''));
            foreach ($doc['changes'] as $c) {
                if ($c['change'] !== 'kept') {
                    $this->line(sprintf('  %-8s %s (%s) %s', $c['change'], $c['key'], $c['type'], $c['heading'] ?? ''));
                }
            }
        }
        foreach ($plan->warnings as $w) {
            $this->warn("warning {$w['path']}: {$w['message']}");
        }

        return $plan->valid() ? self::SUCCESS : $this->errors($plan->errors);
    }

    private function export(PackageExporter $exporter): int
    {
        $keys = $this->option('sections') ? array_values(array_filter(array_map('trim', explode(',', $this->option('sections'))))) : null;
        try {
            if ($slug = $this->option('lesson')) {
                $lesson = Lesson::query()->where('slug', $slug)->first();
                $envelope = $lesson ? $exporter->lesson($lesson, $keys) : null;
            } elseif ($slug = $this->option('post')) {
                $post = Post::query()->where('slug', $slug)->first();
                $envelope = $post ? $exporter->post($post, $keys) : null;
            } else {
                return $this->refuse('Export needs --lesson=<slug> or --post=<slug>.');
            }
        } catch (ValidationException $e) {
            return $this->errors($e->errors());
        }
        if ($envelope === null) {
            return $this->refuse("Nothing has the slug \"{$slug}\".");
        }

        return $this->emit(PackageExporter::json($envelope));
    }

    private function overview(): int
    {
        $course = Course::query()->where('slug', (string) $this->option('course'))->first();
        if (! $course) {
            return $this->refuse('Give --course=<slug> of an existing course.');
        }
        $data = app(CourseOverview::class)->build($course);

        return $this->emit($this->option('json') ? PackageExporter::json($data) : CourseOverview::markdown($data));
    }

    private function emit(string $text): int
    {
        if ($out = $this->option('out')) {
            if (@file_put_contents($out, $text) === false) {
                return $this->refuse("Could not write {$out}.");
            }
            $this->info("Wrote {$out}.");
        } else {
            $this->output->write($text);
        }

        return self::SUCCESS;
    }

    /** @param  array<string, list<string>>  $errors */
    private function errors(array $errors): int
    {
        foreach ($errors as $path => $messages) {
            foreach ($messages as $message) {
                $this->error("{$path}: {$message}");
            }
        }

        return self::FAILURE;
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
