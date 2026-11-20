<?php

namespace App\Modules\Content\Console;

use Database\Seeders\ContentSeeder;
use Illuminate\Console\Command;

/**
 * Imports the content files. The same as `db:seed --class=ContentSeeder`, plus --force to
 * overwrite lessons that were edited in the admin console (normally skipped, ARCHITECTURE D19).
 */
final class ImportContentCommand extends Command
{
    protected $signature = 'content:import {--force : Also overwrite lessons edited in the admin console}';

    protected $description = 'Import courses, lessons, topics and careers from database/seeders/content';

    public function handle(): int
    {
        if ($this->option('force') && ! $this->confirm('Overwrite lessons edited in the admin console with the content files?', false)) {
            return self::FAILURE;
        }

        $seeder = $this->laravel->make(ContentSeeder::class);
        $seeder->force = (bool) $this->option('force');
        $seeder->setContainer($this->laravel)->setCommand($this)->__invoke();

        return self::SUCCESS;
    }
}
