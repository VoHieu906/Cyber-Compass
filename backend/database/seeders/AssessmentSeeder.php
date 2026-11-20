<?php

namespace Database\Seeders;

use App\Modules\Assessment\Services\AssessmentLoader;
use Illuminate\Database\Seeder;

/**
 * Loads the knowledge assessment from database/seeders/content/assessment.php through the
 * Assessment module (ARCHITECTURE D24). Runs after ContentSeeder, which provides the domains.
 */
class AssessmentSeeder extends Seeder
{
    public function run(AssessmentLoader $loader): void
    {
        $report = $loader->load(require database_path('seeders/content/assessment.php'));

        $this->command?->info(sprintf('Assessment: %s, %d questions.', $report['assessment'], $report['questions']));
        if ($report['archived']) {
            $this->command?->warn('Archived (no longer in the assessment file): '.implode(', ', $report['archived']));
        }
    }
}
