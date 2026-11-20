<?php

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
});

it('lists entries newest first with actor and subject labels', function () {
    $lee = User::factory()->create(['name' => 'Lee Learner']);
    $sam = User::factory()->create(['name' => 'Sam Student']);

    $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$lee->id}/suspend", ['reason' => 'First reason']);
    $this->actingAs($this->admin, 'web')->patchJson("/api/v1/admin/users/{$sam->id}/role", ['role' => 'admin']);

    $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/audit-logs')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.action', 'user.role_changed')
        ->assertJsonPath('data.0.subject.label', 'Sam Student')
        ->assertJsonPath('data.1.action', 'user.suspended')
        ->assertJsonPath('data.1.actor.name', 'Ada Admin')
        ->assertJsonPath('data.1.changes.reason', 'First reason');
});

it('filters by actor, subject and action', function () {
    $lee = User::factory()->create();
    $other = User::factory()->admin()->create();
    $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/users/{$lee->id}/suspend", ['reason' => 'Some reason']);
    Auth::forgetGuards(); // the sanctum guard caches the first acting user
    $this->actingAs($other, 'web')->postJson("/api/v1/admin/users/{$lee->id}/reactivate");
    Auth::forgetGuards();

    $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/audit-logs?filter[actor]={$other->id}")
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.action', 'user.reactivated');
    $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/audit-logs?filter[subject_type]=user&filter[subject_id]={$lee->id}")
        ->assertJsonCount(2, 'data');
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/audit-logs?filter[action]=user.suspended')
        ->assertJsonCount(1, 'data');
    $this->actingAs($this->admin, 'web')->getJson('/api/v1/admin/audit-logs?filter[subject_type]=planet')
        ->assertUnprocessable();
});

it('never stores secrets, at any depth', function () {
    $entry = app(AuditLogger::class)->record($this->admin, 'user.test', 'user', 1, [
        'name' => ['Old', 'New'],
        'password' => 'hunter2',
        'nested' => ['remember_token' => 'abc', 'Token' => 'xyz', 'kept' => 1],
    ]);

    expect($entry->fresh()->changes)->toBe(['name' => ['Old', 'New'], 'nested' => ['kept' => 1]]);
});

it('is append-only', function () {
    $entry = app(AuditLogger::class)->record($this->admin, 'user.test', 'user', 1);

    expect(fn () => $entry->update(['action' => 'user.other']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(AdminAuditLog::query()->count())->toBe(1);
});
