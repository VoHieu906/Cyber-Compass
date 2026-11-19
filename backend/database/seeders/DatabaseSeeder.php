<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed development and demonstration data. Content seeders (domains, careers,
     * courses, lessons, assessment) are added here in Phase 3 and 4.
     */
    public function run(): void
    {
        $this->call([
            DemoUserSeeder::class,
        ]);
    }
}
