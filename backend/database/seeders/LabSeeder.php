<?php

namespace Database\Seeders;

use App\Modules\Labs\Services\LabFiles;
use App\Modules\Labs\Services\LabLoader;
use Illuminate\Database\Seeder;

/**
 * Loads the practical labs from database/seeders/content/labs.php through the Labs module
 * (ARCHITECTURE §7). Runs after the lessons, which labs link to.
 */
class LabSeeder extends Seeder
{
    public function run(LabLoader $loader): void
    {
        $files = LabFiles::read();
        $report = $loader->load($files['labs']);

        $this->command?->info(sprintf('Labs: %d.', $report['labs']));
        if ($files['shadowed']) {
            $this->command?->warn('labs/<slug>.php wins over labs.php for: '.implode(', ', $files['shadowed']).'. Remove those entries from labs.php.');
        }
        if ($report['skipped']) {
            $this->command?->warn('Edited in the admin console, not overwritten: '.implode(', ', $report['skipped']));
        }
        if ($report['archived']) {
            $this->command?->warn('Archived (no longer in the lab file): '.implode(', ', $report['archived']));
        }
    }
}
