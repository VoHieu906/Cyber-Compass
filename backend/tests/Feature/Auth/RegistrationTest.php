<?php

use App\Enums\UserRole;
use App\Models\User;

$valid = fn (array $overrides = []) => [
    'name' => 'Lan Nguyen',
    'email' => 'Lan@Example.com',
    'password' => 'secure-pass-1',
    'password_confirmation' => 'secure-pass-1',
    ...$overrides,
];

it('creates an account with a profile and signs it in', function () use ($valid) {
    $this->postJson('/api/v1/auth/register', $valid())
        ->assertCreated()
        ->assertJsonPath('data.email', 'lan@example.com')
        ->assertJsonPath('data.role', 'learner')
        ->assertJsonPath('data.profile.locale', 'en')
        ->assertJsonMissingPath('data.password');

    $user = User::query()->where('email', 'lan@example.com')->firstOrFail();
    expect($user->profile)->not->toBeNull();
    $this->assertAuthenticatedAs($user, 'web');
});

it('starts the profile in the language the visitor was using', function () use ($valid) {
    $this->withHeader('Accept-Language', 'vi')
        ->postJson('/api/v1/auth/register', $valid())
        ->assertCreated()
        ->assertJsonPath('data.profile.locale', 'vi');
});

it('ignores attempts to self-assign a role', function () use ($valid) {
    $this->postJson('/api/v1/auth/register', $valid(['role' => 'admin']))->assertCreated();

    expect(User::query()->firstOrFail()->role)->toBe(UserRole::Learner);
});

it('rejects a taken email regardless of case', function () use ($valid) {
    User::factory()->create(['email' => 'lan@example.com']);

    $this->postJson('/api/v1/auth/register', $valid())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('requires a password of 8+ characters with letters and numbers, confirmed', function (array $input, string $field) use ($valid) {
    $this->postJson('/api/v1/auth/register', $valid($input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'too short' => [['password' => 'ab1', 'password_confirmation' => 'ab1'], 'password'],
    'no number' => [['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'], 'password'],
    'no letter' => [['password' => '1234567890', 'password_confirmation' => '1234567890'], 'password'],
    'mismatch' => [['password_confirmation' => 'different-1'], 'password'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'missing name' => [['name' => '   '], 'name'],
]);

it('localizes validation messages', function () use ($valid) {
    $this->withHeader('Accept-Language', 'vi')
        ->postJson('/api/v1/auth/register', $valid(['email' => 'not-an-email']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Dữ liệu chưa hợp lệ.')
        ->assertJsonPath('errors.email.0', 'email phải là địa chỉ email hợp lệ.');
});
