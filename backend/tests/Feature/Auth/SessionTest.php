<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'learner@example.com',
        'password' => 'secure-pass-1',
    ]);
});

it('logs in with valid credentials and returns the user', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'Learner@Example.com', 'password' => 'secure-pass-1'])
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.profile.theme', 'system');

    $this->assertAuthenticatedAs($this->user, 'web');
});

it('rejects a wrong password without saying which field was wrong', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'learner@example.com', 'password' => 'wrong-pass-1'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', __('auth.failed'));

    $this->assertGuest('web');
});

it('answers an unknown email exactly like a wrong password', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'secure-pass-1'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', __('auth.failed'));
});

it('locks the account and IP after five failed attempts', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'learner@example.com', 'password' => 'wrong-pass-1']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'learner@example.com', 'password' => 'secure-pass-1'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertJsonValidationErrors('email');

    $this->assertGuest('web');
});

it('limits one address trying many accounts (password spraying)', function () {
    foreach (range(1, 20) as $i) {
        $this->postJson('/api/v1/auth/login', ['email' => "spray{$i}@example.com", 'password' => 'wrong-pass-1'])
            ->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'spray21@example.com', 'password' => 'wrong-pass-1'])
        ->assertStatus(429);
});

it('sets a remember-me cookie only when asked', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'learner@example.com', 'password' => 'secure-pass-1', 'remember' => true])
        ->assertOk()
        ->assertCookie(Auth::guard('web')->getRecallerName());
});

it('does not set a remember-me cookie by default', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'learner@example.com', 'password' => 'secure-pass-1'])
        ->assertOk()
        ->assertCookieMissing(Auth::guard('web')->getRecallerName());
});

it('returns the current user for an authenticated session', function () {
    $this->actingAs($this->user, 'web')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'learner@example.com')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');
});

it('rejects /me without a session', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('logs out and ends the session', function () {
    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertGuest('web');
});

it('requires a session to log out', function () {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

it('records when the user last signed in', function () {
    expect($this->user->last_login_at)->toBeNull();

    $this->postJson('/api/v1/auth/login', ['email' => 'learner@example.com', 'password' => 'secure-pass-1'])->assertOk();

    expect($this->user->refresh()->last_login_at)->not->toBeNull();
});
