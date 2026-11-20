<?php

use App\Enums\UserRole;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Modules\Admin\Actions\ChangeUserRole;
use App\Modules\Admin\Actions\SuspendUser;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin', 'email' => 'ada@example.com']);
    $this->learner = User::factory()->create(['name' => 'Lee Learner', 'email' => 'lee@example.com']);
});

/** Pretend the user has a live database session, as a signed-in browser would. */
function giveSession(User $user): void
{
    DB::table('sessions')->insert([
        'id' => Str::random(40), 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
    ]);
}

describe('listing', function () {
    it('searches, filters and paginates users', function () {
        User::factory()->suspended()->create(['name' => 'Sam Suspended']);

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/users?q=lee')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'lee@example.com')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/users?filter[status]=suspended')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sam Suspended');

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/users?filter[role]=admin')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_self', true);

        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/users?sort=name&per_page=2')
            ->assertJsonPath('data.0.name', 'Ada Admin')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2);
    });

    it('never exposes passwords, tokens or private contact details', function () {
        $json = $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/users/{$this->learner->id}")->json('data');

        expect($json)->not->toHaveKeys(['password', 'remember_token', 'phone', 'date_of_birth']);
    });

    it('validates list parameters', function () {
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/users?sort=password&filter[role]=root')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'filter.role']);
    });

    it('shows a user with learning totals and recent admin activity', function () {
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/suspend", ['reason' => 'Spam in forums']);

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/users/{$this->learner->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.suspension.reason', 'Spam in forums')
            ->assertJsonPath('data.stats.enrollments', 0)
            ->assertJsonPath('data.recent_activity.0.action', 'user.suspended')
            ->assertJsonPath('data.recent_activity.0.actor.name', 'Ada Admin');
    });

    it('answers 404 for an unknown user', function () {
        $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/users/999999')->assertNotFound();
    });
});

describe('roles', function () {
    it('promotes a learner, ends their sessions and records it', function () {
        giveSession($this->learner);
        $token = $this->learner->remember_token;

        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/admin/users/{$this->learner->id}/role", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->learner->refresh();
        expect($this->learner->role)->toBe(UserRole::Admin)
            ->and($this->learner->remember_token)->not->toBe($token)
            ->and(DB::table('sessions')->where('user_id', $this->learner->id)->count())->toBe(0);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->admin->id, 'action' => 'user.role_changed', 'subject_type' => 'user', 'subject_id' => $this->learner->id,
        ]);
        expect(AdminAuditLog::query()->latest('id')->first()->changes)->toBe(['role' => ['learner', 'admin']]);
    });

    it('does not let an admin demote themselves', function () {
        User::factory()->admin()->create(); // a second admin exists, so only the self rule applies

        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/admin/users/{$this->admin->id}/role", ['role' => 'learner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        expect($this->admin->refresh()->role)->toBe(UserRole::Admin);
    });

    it('lets one admin demote another while two are active', function () {
        $other = User::factory()->admin()->create();

        $this->actingAs($other, 'web')->patchJson("/api/v1/admin/users/{$this->admin->id}/role", ['role' => 'learner'])
            ->assertOk()
            ->assertJsonPath('data.role', 'learner');
    });

    it('protects the last active admin', function () {
        // Over HTTP the acting admin is always a second active admin, so this rule is the
        // backstop for concurrent demotions; exercise it on the actions directly.
        User::factory()->admin()->suspended()->create(); // suspended admins do not count
        $actor = User::factory()->create();

        expect(fn () => app(ChangeUserRole::class)->handle($actor, $this->admin, UserRole::Learner))
            ->toThrow(ValidationException::class, 'last active admin');
        expect(fn () => app(SuspendUser::class)->handle($actor, $this->admin, 'No admins left'))
            ->toThrow(ValidationException::class, 'last active admin');

        $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/users/{$this->admin->id}")
            ->assertJsonPath('data.is_last_active_admin', true);
    });

    it('rejects an unknown role', function () {
        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/admin/users/{$this->learner->id}/role", ['role' => 'root'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    });

    it('leaves an unchanged role alone and writes no audit entry', function () {
        $this->actingAs($this->admin, 'web')->patchJson("/api/v1/admin/users/{$this->learner->id}/role", ['role' => 'learner'])
            ->assertOk();

        expect(AdminAuditLog::query()->count())->toBe(0);
    });
});

describe('suspension', function () {
    it('suspends a user, ends their sessions and blocks sign-in', function () {
        $this->learner->forceFill(['password' => 'secure-pass-1'])->save();
        giveSession($this->learner);

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/suspend", ['reason' => 'Shared account'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        expect(DB::table('sessions')->where('user_id', $this->learner->id)->count())->toBe(0);
        Auth::forgetGuards();

        $this->postJson('/api/v1/auth/login', ['email' => 'lee@example.com', 'password' => 'secure-pass-1'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.suspended'));
        $this->assertGuest('web');
    });

    it('does not reveal a suspension to someone with the wrong password', function () {
        $this->learner->forceFill(['suspended_at' => now()])->save();

        $this->postJson('/api/v1/auth/login', ['email' => 'lee@example.com', 'password' => 'wrong-pass-1'])
            ->assertJsonPath('errors.email.0', __('auth.failed'));
    });

    it('signs out a suspended user who still holds a session', function () {
        $this->learner->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($this->learner, 'web')->getJson('/api/v1/me')->assertUnauthorized();
    });

    it('does not let an admin suspend themselves', function () {
        User::factory()->admin()->create();

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->admin->id}/suspend", ['reason' => 'Testing myself'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    });

    it('requires a reason and refuses to suspend twice', function () {
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/suspend", [])
            ->assertJsonValidationErrors('reason');

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/suspend", ['reason' => 'First time']);
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/suspend", ['reason' => 'Second time'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    });

    it('reactivates a suspended user', function () {
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/suspend", ['reason' => 'Temporary hold']);

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.suspension', null);

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/reactivate")
            ->assertUnprocessable();
    });
});

describe('password reset', function () {
    it('sends the standard reset email without exposing anything', function () {
        Notification::fake();

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/password-reset")
            ->assertStatus(202)
            ->assertJsonMissingPath('data.token');

        Notification::assertSentTo($this->learner, ResetPassword::class);
        $entry = AdminAuditLog::query()->where('action', 'user.password_reset_sent')->firstOrFail();
        expect($entry->changes)->toBeNull();
    });

    it('throttles repeated reset emails', function () {
        Notification::fake();

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/password-reset")->assertStatus(202);
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/password-reset")->assertTooManyRequests();
    });

    it('refuses for a suspended account', function () {
        $this->learner->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$this->learner->id}/password-reset")
            ->assertUnprocessable();
    });
});
