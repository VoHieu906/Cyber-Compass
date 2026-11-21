<?php

namespace App\Modules\Admin\Console;

use Database\Seeders\ContentSeeder;
use Illuminate\Console\Command;

/**
 * One deployment step, run as a one-off ECS task from the api image before the services update
 * (ARCHITECTURE §8): the production preflight, the migrations, then the learning content
 * (courses, topics, careers, exercises, labs, the assessment bank; D19, D24, D29). It never seeds
 * the demo accounts. Every step is idempotent, so a failed release can simply be run again.
 *
 *   php artisan cybercompass:release
 */
final class ReleaseCommand extends Command
{
    protected $signature = 'cybercompass:release';

    protected $description = 'Preflight, migrate and import the learning content (the deployment task)';

    public function handle(): int
    {
        if ($this->call('cybercompass:preflight') !== self::SUCCESS) {
            $this->error('Release stopped before changing anything.');

            return self::FAILURE;
        }

        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->call('db:seed', ['--class' => ContentSeeder::class, '--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Release complete.');

        return self::SUCCESS;
    }
}
