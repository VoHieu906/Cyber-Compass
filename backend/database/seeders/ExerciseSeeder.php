<?php

namespace Database\Seeders;

use App\Modules\Learning\Services\ExerciseLoader;
use Illuminate\Database\Seeder;

/**
 * Loads lesson exercises from database/seeders/content/exercises/*.php through the Learning
 * module (ARCHITECTURE D29). Runs after the lessons are imported, because questions point at
 * their sections.
 */
class ExerciseSeeder extends Seeder
{
    public function run(ExerciseLoader $loader): void
    {
        $lessons = [];
        foreach (glob(database_path('seeders/content/exercises/*.php')) as $file) {
            foreach (require $file as $slug => $exercise) {
                if (isset($lessons[$slug])) {
                    throw new \InvalidArgumentException("Exercise for {$slug} is defined in more than one file.");
                }
                $lessons[$slug] = $exercise;
            }
        }

        $report = $loader->load($lessons);
        $this->command?->info(sprintf('Exercises: %d lessons, %d questions.', $report['exercises'], $report['questions']));
        if ($report['archived']) {
            $this->command?->warn('Archived (no longer in the exercise files): '.implode(', ', $report['archived']));
        }
    }
}
