<?php

namespace App\Modules\Labs\Console;

use App\Modules\Labs\Services\LabFiles;
use App\Modules\Labs\Services\LabLoader;
use Illuminate\Console\Command;

/**
 * Loads the lab content files (`labs.php`, `labs/<slug>.php`) on their own, like the lab part of
 * `db:seed`. Labs edited in the admin console are skipped unless --force (D73).
 */
final class ImportLabsCommand extends Command
{
    protected $signature = 'content:import-labs {--force : Also overwrite labs edited in the admin console}';

    protected $description = 'Import the labs from their content files';

    public function handle(LabLoader $loader): int
    {
        $files = LabFiles::read();
        foreach ($files['shadowed'] as $slug) {
            $this->warn("labs/{$slug}.php wins over the \"{$slug}\" entry in labs.php. To finish the export, delete that entry from database/seeders/content/labs.php.");
        }
        $report = $loader->load($files['labs'], (bool) $this->option('force'));
        $this->info("Labs imported: {$report['labs']}.");
        if ($report['skipped']) {
            $this->warn('Edited in the admin console, not overwritten (use --force): '.implode(', ', $report['skipped']));
        }
        if ($report['archived']) {
            $this->warn('Archived (no longer in the files): '.implode(', ', $report['archived']));
        }

        return self::SUCCESS;
    }
}
