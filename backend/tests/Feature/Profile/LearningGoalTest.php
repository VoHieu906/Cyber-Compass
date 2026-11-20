<?php

use App\Models\Career;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires a session', function () {
    $this->putJson('/api/v1/me/learning-goal', [])->assertUnauthorized();
});

it('has no goal until one is set', function () {
    $this->actingAs($this->user, 'web')->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.learning_goal', null);
});

it('saves a full goal and returns it on me', function () {
    $this->actingAs($this->user, 'web')
        ->putJson('/api/v1/me/learning-goal', [
            'career' => 'soc-analyst',
            'self_level' => 'intermediate',
            'weekly_minutes' => 300,
            'domains' => ['linux', 'networking'],
        ])
        ->assertOk()
        ->assertJsonPath('data.learning_goal.career', ['slug' => 'soc-analyst', 'title' => 'SOC Analyst'])
        ->assertJsonPath('data.learning_goal.self_level', 'intermediate')
        ->assertJsonPath('data.learning_goal.weekly_minutes', 300)
        // Domains come back in display order.
        ->assertJsonPath('data.learning_goal.domains', ['networking', 'linux']);

    $this->getJson('/api/v1/me')->assertJsonPath('data.learning_goal.career.slug', 'soc-analyst');
});

it('changes only the fields sent, and clears a field sent as null', function () {
    $this->actingAs($this->user, 'web')->putJson('/api/v1/me/learning-goal', ['career' => 'soc-analyst', 'weekly_minutes' => 120, 'domains' => ['linux']]);

    $this->putJson('/api/v1/me/learning-goal', ['career' => 'penetration-tester'])
        ->assertJsonPath('data.learning_goal.career.slug', 'penetration-tester')
        ->assertJsonPath('data.learning_goal.weekly_minutes', 120)
        ->assertJsonPath('data.learning_goal.domains', ['linux']);

    $this->putJson('/api/v1/me/learning-goal', ['career' => null, 'domains' => []])
        ->assertJsonPath('data.learning_goal.career', null)
        ->assertJsonPath('data.learning_goal.domains', []);
});

it('validates the goal', function (array $body, string $field) {
    $this->actingAs($this->user, 'web')->putJson('/api/v1/me/learning-goal', $body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'unknown career' => [['career' => 'astronaut'], 'career'],
    'unknown level' => [['self_level' => 'expert'], 'self_level'],
    'too little time' => [['weekly_minutes' => 10], 'weekly_minutes'],
    'too much time' => [['weekly_minutes' => 5000], 'weekly_minutes'],
    'unknown domain' => [['domains' => ['astrology']], 'domains.0'],
    'repeated domain' => [['domains' => ['linux', 'linux']], 'domains.0'],
]);

it('refuses an unpublished career', function () {
    Career::query()->where('slug', 'soc-analyst')->update(['is_published' => false]);

    $this->actingAs($this->user, 'web')->putJson('/api/v1/me/learning-goal', ['career' => 'soc-analyst'])->assertJsonValidationErrors('career');
});
