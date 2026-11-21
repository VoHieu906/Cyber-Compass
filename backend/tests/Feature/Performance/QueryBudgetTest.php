<?php

use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\DB;

/*
| Query budgets for the hot endpoints (Phase 7 Part D, docs/PERFORMANCE.md). Run against the real
| content and the demo learner (a goal, an assessment, a roadmap and progress). Strict mode already
| makes a lazily loaded relation throw in tests; these checks also catch queries issued in loops:
| each endpoint stays within its budget, and its count does not grow with the learner's history.
*/

/** Endpoint => most queries one request may run (measured 2026-11-21, plus headroom). */
const QUERY_BUDGETS = [
    '/api/v1/me' => 3,
    '/api/v1/progress/summary' => 30,
    '/api/v1/progress/activity?range=12w' => 6,
    '/api/v1/progress/next' => 16,
    '/api/v1/progress/recent' => 9,
    '/api/v1/progress/assessments' => 7,
    '/api/v1/progress/skills' => 15,
    '/api/v1/progress/courses' => 3,
    '/api/v1/notifications/unread-count' => 3,
    '/api/v1/notifications' => 4,
    '/api/v1/learning-path' => 16,
    '/api/v1/knowledge-map' => 17,
    '/api/v1/lessons/osi-model' => 16,
    '/api/v1/lessons/osi-model/exercise' => 11,
    '/api/v1/courses' => 5,
    '/api/v1/courses/networking-fundamentals' => 16,
    '/api/v1/careers' => 4,
    '/api/v1/careers/soc-analyst' => 10,
    '/api/v1/search?q=net' => 8,
    '/api/v1/labs' => 6,
    '/api/v1/domains' => 3,
];

function demoLearner(): User
{
    config(['cybercompass.demo_user_password' => 'local-only-pass-1', 'cybercompass.demo_admin_password' => null]);
    test()->seed(DemoUserSeeder::class);

    return User::query()->where('email', 'demo@cybercompass.test')->firstOrFail();
}

/** Queries one request runs, after a warm-up request so one-time queries do not count. */
function queriesFor(string $uri): int
{
    test()->getJson($uri)->assertOk();
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });
    test()->getJson($uri)->assertOk();

    return $count;
}

/** @return array<string, int> */
function measureAll(): array
{
    $counts = [];
    foreach (array_keys(QUERY_BUDGETS) as $uri) {
        $counts[$uri] = queriesFor($uri);
    }

    return $counts;
}

it('keeps every hot endpoint within its query budget', function () {
    $this->actingAs(demoLearner());

    foreach (measureAll() as $uri => $count) {
        expect($count)->toBeLessThanOrEqual(QUERY_BUDGETS[$uri], "{$uri} ran {$count} queries");
    }
});

it('runs the same number of queries however much the learner has done', function () {
    $user = demoLearner();
    $this->actingAs($user);
    $before = measureAll();

    // More history everywhere at once: progress, activities, course progress and, through the
    // listeners, notifications and milestones.
    $lessons = Lesson::query()->visibleToLearners()->whereNotIn('slug', ['cia-triad'])->orderBy('id')->limit(6)->get();
    foreach ($lessons as $lesson) {
        passLesson($user, $lesson);
    }
    $this->actingAs($user->refresh());
    expect($user->notifications()->count())->toBeGreaterThanOrEqual(6)
        ->and($user->learningActivities()->count())->toBeGreaterThanOrEqual(6);

    expect(measureAll())->toBe($before);
});
