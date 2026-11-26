<?php

namespace App\Modules\Labs\Console;

use App\Models\Lab;
use App\Modules\Labs\Services\LabDefinition;
use App\Modules\Labs\Services\LabDefinitionValidator;
use Illuminate\Console\Command;

/**
 * The lab health check (F7, D78): validates every stored lab, and every unpublished draft, with all
 * reference solutions and decoys replayed. A lab that stops being solvable (an engine changed under
 * it, a content file edited) is found here rather than by a learner. Messages name places, never
 * answers. `cybercompass:release` runs it with --warn-only after the content import.
 *
 *   php artisan content:check-labs [--warn-only]
 */
final class CheckLabsCommand extends Command
{
    protected $signature = 'content:check-labs {--warn-only : Report errors but always exit 0 (the release task)}';

    protected $description = 'Validate every lab and replay its reference solutions';

    public function handle(LabDefinitionValidator $validator): int
    {
        $problems = 0;
        $checked = 0;
        foreach (Lab::query()->with(['draft', 'domain:id,slug', 'lesson:id,slug'])->orderBy('position')->get() as $lab) {
            $versions = ['live' => $lab->status === 'published' ? LabDefinition::fromLab($lab) : null, 'draft' => $lab->draft ? LabDefinition::normalize($lab->draft->snapshot) : null];
            foreach (array_filter($versions) as $which => $def) {
                $checked++;
                $report = $validator->check($def, replay: true);
                foreach ($report['errors'] as $path => $messages) {
                    foreach ($messages as $message) {
                        $problems++;
                        $this->error("{$lab->slug} ({$which}): {$path}: {$message}");
                    }
                }
                foreach ($report['warnings'] as $path => $messages) {
                    foreach ($messages as $message) {
                        $this->warn("{$lab->slug} ({$which}): {$path}: {$message}");
                    }
                }
            }
        }
        $this->info("Checked {$checked} lab versions; {$problems} errors.");

        return $problems > 0 && ! $this->option('warn-only') ? self::FAILURE : self::SUCCESS;
    }
}
