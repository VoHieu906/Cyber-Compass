<?php

use App\Models\Lesson;
use App\Models\User;

/*
| Every admin route answers 401 to guests and 403 to learners (docs/PHASE_3_5_CONTENT_AND_ADMIN.md §8).
| New admin routes are covered automatically: the list is read from the router.
*/

$adminRoutes = function () {
    return collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/admin'))
        ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => $m === 'HEAD')
            ->map(fn ($m) => [$m, '/'.preg_replace('/\{[^}]+\}/', '1', $r->uri())]))
        ->values();
};

it('has admin routes to protect', function () use ($adminRoutes) {
    expect($adminRoutes()->count())->toBeGreaterThanOrEqual(8);
});

it('answers 401 to guests on every admin route', function () use ($adminRoutes) {
    foreach ($adminRoutes() as [$method, $uri]) {
        $this->json($method, $uri)->assertUnauthorized();
    }
});

it('answers 403 to learners on every admin route', function () use ($adminRoutes) {
    $learner = User::factory()->create();

    foreach ($adminRoutes() as [$method, $uri]) {
        $this->actingAs($learner, 'web')->json($method, $uri)
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }
});

it('lets admins in', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/overview')
        ->assertOk()
        ->assertJsonPath('data.users.admins', 1)
        ->assertJsonPath('data.lessons.published', Lesson::query()->where('status', 'published')->count())
        ->assertJsonPath('data.lessons.draft', 0);
});

it('rate limits the admin API per admin', function () {
    $admin = User::factory()->admin()->create();

    foreach (range(1, 120) as $_) {
        $this->actingAs($admin, 'web')->getJson('/api/v1/admin/audit-logs?per_page=1');
    }

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/audit-logs?per_page=1')->assertTooManyRequests();
});
