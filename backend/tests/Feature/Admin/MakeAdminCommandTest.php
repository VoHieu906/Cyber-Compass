<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;

it('promotes an existing account and records it with no actor', function () {
    $user = User::factory()->create(['email' => 'first@example.com']);

    $this->artisan('cybercompass:make-admin', ['email' => 'First@Example.com'])
        ->expectsOutputToContain('is now an admin')
        ->assertSuccessful();

    expect($user->refresh()->role)->toBe(UserRole::Admin);
    $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => null, 'action' => 'user.promoted_from_cli', 'subject_id' => $user->id]);
});

it('also recovers a suspended admin', function () {
    $user = User::factory()->admin()->suspended()->create(['email' => 'locked@example.com']);

    $this->artisan('cybercompass:make-admin', ['email' => 'locked@example.com'])->assertSuccessful();

    expect($user->refresh()->isSuspended())->toBeFalse();
});

it('never creates accounts', function () {
    $this->artisan('cybercompass:make-admin', ['email' => 'nobody@example.com'])->assertFailed();

    $this->assertDatabaseMissing('users', ['email' => 'nobody@example.com']);
});

it('seeds the demo admin only when its password is configured', function () {
    config(['cybercompass.demo_admin_password' => null, 'cybercompass.demo_user_password' => null]);
    $this->seed(DemoUserSeeder::class);
    $this->assertDatabaseMissing('users', ['email' => 'admin@cybercompass.test']);

    config(['cybercompass.demo_admin_password' => 'local-only-pass-1']);
    $this->seed(DemoUserSeeder::class);
    expect(User::query()->where('email', 'admin@cybercompass.test')->value('role'))->toBe(UserRole::Admin);
});
