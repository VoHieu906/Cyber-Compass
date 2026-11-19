<?php

use App\Models\Course;
use App\Models\User;

it('lists published courses with card data, newest first, 6 per page', function () {
    $response = $this->getJson('/api/v1/courses')->assertOk();

    $response->assertJsonCount(6, 'data')
        ->assertJsonPath('meta.total', 8)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.slug', 'system-hardening')
        ->assertJsonStructure(['data' => [['slug', 'title', 'summary', 'domain' => ['slug', 'name'], 'difficulty', 'lesson_count', 'estimated_minutes']]]);

    expect($response->json('data.0'))->not->toHaveKey('is_favorited');
});

it('hides unpublished courses', function () {
    Course::query()->where('slug', 'system-hardening')->update(['is_published' => false]);

    $this->getJson('/api/v1/courses')->assertJsonPath('meta.total', 7);
    $this->getJson('/api/v1/courses/system-hardening')->assertNotFound();
});

it('searches title and summary case-insensitively, including partial words', function () {
    $this->getJson('/api/v1/courses?q=NETW')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'networking-fundamentals');
});

it('treats search wildcards literally', function () {
    $this->getJson('/api/v1/courses?q=%25')->assertOk()->assertJsonCount(0, 'data');
});

it('filters by domain and difficulty', function () {
    $this->getJson('/api/v1/courses?filter[domain]=web-security')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'web-security-foundations');

    $intermediate = $this->getJson('/api/v1/courses?filter[difficulty]=intermediate&per_page=50')->json('data');
    expect(collect($intermediate)->pluck('difficulty')->unique()->all())->toBe(['intermediate'])
        ->and($intermediate)->toHaveCount(3);
});

it('sorts by duration and by title', function () {
    $durations = collect($this->getJson('/api/v1/courses?sort=-duration&per_page=50')->json('data'))->pluck('estimated_minutes');
    expect($durations->all())->toBe($durations->sortDesc()->values()->all())
        ->and($durations->first())->toBe(90);

    $titles = collect($this->getJson('/api/v1/courses?sort=title&per_page=50')->json('data'))->pluck('title');
    expect($titles->all())->toBe($titles->sort()->values()->all());
});

it('rejects unknown filters and sorts', function () {
    $this->getJson('/api/v1/courses?sort=price')->assertUnprocessable()->assertJsonValidationErrors('sort');
    $this->getJson('/api/v1/courses?filter[domain]=astrology')->assertUnprocessable()->assertJsonValidationErrors('filter.domain');
});

it('marks favorites for a signed-in viewer and filters by them', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'web')->postJson('/api/v1/courses/applied-cryptography/favorite')->assertOk();

    $all = collect($this->actingAs($user, 'web')->getJson('/api/v1/courses?per_page=50')->json('data'));
    expect($all->firstWhere('slug', 'applied-cryptography')['is_favorited'])->toBeTrue()
        ->and($all->where('is_favorited', true))->toHaveCount(1);

    $this->actingAs($user, 'web')
        ->getJson('/api/v1/courses?filter[favorited]=1')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'applied-cryptography');
});

it('returns nothing for the favorites filter when signed out', function () {
    $this->getJson('/api/v1/courses?filter[favorited]=1')->assertOk()->assertJsonCount(0, 'data');
});

it('shows a course with chapters, lessons, objectives, skills, prerequisites and related courses', function () {
    $this->getJson('/api/v1/courses/web-security-foundations')
        ->assertOk()
        ->assertJsonPath('data.lesson_count', 4)
        ->assertJsonCount(2, 'data.chapters')
        ->assertJsonPath('data.chapters.0.lessons.0.slug', 'http-essentials')
        ->assertJsonPath('data.prerequisites.0.slug', 'networking-fundamentals')
        ->assertJsonPath('data.related.0.slug', 'networking-fundamentals')
        ->assertJsonCount(3, 'data.related')
        ->assertJsonStructure(['data' => ['description', 'objectives', 'skills' => [['slug', 'name']]]]);
});

it('lists domains with their course counts', function () {
    $this->getJson('/api/v1/domains')
        ->assertOk()
        ->assertJsonCount(8, 'data')
        ->assertJsonPath('data.0.slug', 'networking')
        ->assertJsonPath('data.0.course_count', 1);
});
