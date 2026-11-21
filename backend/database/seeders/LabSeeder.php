<?php

namespace Database\Seeders;

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
        $report = $loader->load(require database_path('seeders/content/labs.php'));

        $this->command?->info(sprintf('Labs: %d.', $report['labs']));
        if ($report['archived']) {
            $this->command?->warn('Archived (no longer in the lab file): '.implode(', ', $report['archived']));
        }
    }
}
