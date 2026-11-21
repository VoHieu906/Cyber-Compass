<?php

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

/*
| The deployment task (ARCHITECTURE §8): preflight, migrate, import the content, never demo users.
*/

it('stops before changing anything when the preflight fails', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => true]);
    $migrated = false;
    Artisan::command('migrate {--force}', function () use (&$migrated) {
        $migrated = true;
    });

    $this->artisan('cybercompass:release')
        ->expectsOutputToContain('Release stopped before changing anything.')
        ->assertFailed();
    expect($migrated)->toBeFalse();
});

it('migrates and imports the content again without demo accounts', function () {
    safeProductionConfig();
    $courses = Course::query()->count();

    $this->artisan('cybercompass:release')
        ->expectsOutputToContain('Release complete.')
        ->assertSuccessful();

    expect(Course::query()->count())->toBe($courses)
        ->and(User::query()->whereIn('email', ['demo@cybercompass.test', 'admin@cybercompass.test'])->exists())->toBeFalse();
});
