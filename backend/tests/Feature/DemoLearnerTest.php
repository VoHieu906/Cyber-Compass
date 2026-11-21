<?php

use App\Enums\LessonProgressStatus;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;

/*
| The demo learner's journey (seeded once): a goal, a submitted assessment, one lesson finished
| by passing its exercise (D31), and a roadmap. Re-seeding leaves the learner alone.
*/

it('seeds the demo learner with a finished lesson, passed the way a learner passes it', function () {
    config(['cybercompass.demo_user_password' => 'local-only-pass-1', 'cybercompass.demo_admin_password' => null]);
    $this->seed(DemoUserSeeder::class);

    $user = User::query()->where('email', 'demo@cybercompass.test')->firstOrFail();
    $progress = $user->lessonProgress()->whereHas('lesson', fn ($q) => $q->where('slug', 'cia-triad'))->sole();

    expect($progress->status)->toBe(LessonProgressStatus::Completed)
        ->and($user->exerciseAttempts()->where('passed', true)->count())->toBe(1)
        ->and($user->learningPaths()->count())->toBe(1);

    $this->seed(DemoUserSeeder::class);
    expect($user->exerciseAttempts()->count())->toBe(1)->and($user->learningPaths()->count())->toBe(1);
});
