<?php

use App\Enums\LessonStatus;
use App\Models\Career;
use App\Models\Course;
use App\Models\Lesson;

it('is open to guests and groups courses, lessons and careers', function () {
    $data = $this->getJson('/api/v1/search?q=network')->assertOk()->json('data');

    expect($data['query'])->toBe('network')
        ->and(array_column($data['courses'], 'slug'))->toContain('networking-fundamentals')
        ->and(array_column($data['lessons'], 'slug'))->toContain('intro-to-computer-networks')
        ->and(array_keys($data['lessons'][0]))->toBe(['slug', 'title', 'summary', 'course'])
        ->and(array_keys($data['courses'][0]))->toBe(['slug', 'title', 'summary', 'domain']);
});

it('matches careers by title and summary, ignoring case', function () {
    $career = Career::query()->published()->firstOrFail();

    $slugs = array_column($this->getJson('/api/v1/search?q='.urlencode(mb_strtoupper(mb_substr($career->title, 0, 6))))->json('data.careers'), 'slug');

    expect($slugs)->toContain($career->slug);
});

it('returns at most five per group, titles that start with the term first', function () {
    $data = $this->getJson('/api/v1/search?q=se')->assertOk()->json('data');

    foreach (['courses', 'lessons', 'careers'] as $group) {
        expect(count($data[$group]))->toBeLessThanOrEqual(5);
    }
    // Once a title that does not start with the term appears, none after it does.
    $starts = array_map(fn ($row) => str_starts_with(mb_strtolower($row['title']), 'se'), $data['lessons']);
    $firstOther = array_search(false, $starts, true);
    expect($firstOther === false ? [] : array_slice($starts, $firstOther))->not->toContain(true);
});

it('never shows drafts, archived lessons, unpublished courses or careers', function () {
    Lesson::query()->where('slug', 'osi-model')->update(['status' => LessonStatus::Draft]);
    Lesson::query()->where('slug', 'subnetting')->update(['status' => LessonStatus::Archived]);
    Course::query()->where('slug', 'web-security-foundations')->update(['is_published' => false]);
    $career = Career::query()->published()->firstOrFail();
    $career->update(['is_published' => false]);

    expect(array_column($this->getJson('/api/v1/search?q=osi')->json('data.lessons'), 'slug'))->not->toContain('osi-model');
    expect(array_column($this->getJson('/api/v1/search?q=subnet')->json('data.lessons'), 'slug'))->not->toContain('subnetting');
    $web = $this->getJson('/api/v1/search?q=web')->json('data');
    expect(array_column($web['courses'], 'slug'))->not->toContain('web-security-foundations')
        ->and(collect($web['lessons'])->pluck('course')->all())->not->toContain('Web Security Foundations');
    expect(array_column($this->getJson('/api/v1/search?q='.urlencode($career->title))->json('data.careers'), 'slug'))->not->toContain($career->slug);
});

it('treats % and _ as plain characters', function () {
    $this->getJson('/api/v1/search?q='.urlencode('%%'))->assertOk()->assertJsonPath('data.courses', [])->assertJsonPath('data.lessons', []);
    $this->getJson('/api/v1/search?q=__')->assertOk()->assertJsonPath('data.careers', []);
});

it('refuses a query that is missing, too short or too long', function (string $query) {
    $this->getJson("/api/v1/search{$query}")->assertUnprocessable()->assertJsonValidationErrors('q');
})->with([
    'missing' => [''],
    'one character' => ['?q=a'],
    'only spaces' => ['?q='.urlencode('   ')],
    'spaces around one character' => ['?q='.urlencode('  a  ')],
    'over 100 characters' => ['?q='.str_repeat('x', 101)],
]);

it('limits searches per address', function () {
    foreach (range(1, 60) as $_) {
        $this->getJson('/api/v1/search?q=network')->assertOk();
    }
    $this->getJson('/api/v1/search?q=network')->assertTooManyRequests();
});
