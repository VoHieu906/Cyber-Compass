<?php

use App\Enums\NotificationType;
use App\Models\Assessment;
use App\Models\User;
use App\Modules\Assessment\Actions\StartAttempt;
use App\Modules\Assessment\Actions\SubmitAttempt;
use App\Modules\Notifications\Notifications\LearningNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Lan']);
});

/** [type, params] of a learner's stored notifications, oldest first. */
function stored(User $user): array
{
    return $user->notifications()->oldest()->get()->map(fn ($n) => [$n->type, $n->data['params']])->all();
}

function submitBlankAssessment(User $user): int
{
    $attempt = app(StartAttempt::class)($user, Assessment::query()->active()->firstOrFail())['attempt'];
    app(SubmitAttempt::class)($attempt);

    return $attempt->id;
}

it('requires a session', function (string $method, string $path) {
    $this->json($method, "/api/v1/{$path}")->assertUnauthorized();
})->with([
    ['GET', 'notifications'],
    ['GET', 'notifications/unread-count'],
    ['POST', 'notifications/read-all'],
    ['POST', 'notifications/0b6c2f1e-5d0a-4f8e-9a51-1f2d3c4b5a69/read'],
    ['DELETE', 'notifications/0b6c2f1e-5d0a-4f8e-9a51-1f2d3c4b5a69'],
    ['GET', 'me/notification-preferences'],
    ['PUT', 'me/notification-preferences'],
]);

it('tells me when I first complete a lesson, not when I review it', function () {
    $this->actingAs($this->user, 'web')->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 60])->assertOk();
    $this->postJson('/api/v1/lessons/linux-logs/complete', ['time_spent_seconds' => 60])->assertOk();

    expect(stored($this->user))->toEqual([['lesson_completed', ['lesson' => 'Finding evidence in logs', 'slug' => 'linux-logs']]]);
});

it('sends my assessment result with a link to it', function () {
    $id = submitBlankAssessment($this->user);

    $this->actingAs($this->user, 'web')->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'assessment_result')
        ->assertJsonPath('data.0.url', "/assessment/attempts/{$id}/result")
        ->assertJsonPath('data.0.read_at', null)
        ->assertJsonPath('meta.total', 1);
    expect($this->getJson('/api/v1/notifications')->json('data.0.params'))->toEqual(['score' => 0, 'band' => 'gap', 'attempt' => $id]);
});

it('lists, counts, reads, reads all and deletes only my notifications', function () {
    passLesson($this->user, 'linux-logs');
    $this->travel(1)->minutes();
    submitBlankAssessment($this->user);
    passLesson($other = User::factory()->create(), 'linux-logs');
    $theirs = $other->notifications()->value('id');

    $this->actingAs($this->user, 'web');
    $list = $this->getJson('/api/v1/notifications')->assertOk()->json('data');
    expect(array_column($list, 'type'))->toBe(['assessment_result', 'lesson_completed']);
    $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 2);

    $this->postJson("/api/v1/notifications/{$list[0]['id']}/read")->assertOk()->assertJsonPath('data.id', $list[0]['id']);
    expect($this->getJson('/api/v1/notifications/unread-count')->json('data.count'))->toBe(1)
        ->and(array_column($this->getJson('/api/v1/notifications?filter[unread]=1')->json('data'), 'type'))->toBe(['lesson_completed']);

    $this->postJson("/api/v1/notifications/{$theirs}/read")->assertNotFound();
    $this->deleteJson("/api/v1/notifications/{$theirs}")->assertNotFound();
    expect($other->unreadNotifications()->count())->toBe(1);

    $this->postJson('/api/v1/notifications/read-all')->assertOk();
    $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 0);

    $this->deleteJson("/api/v1/notifications/{$list[1]['id']}")->assertNoContent();
    expect($this->getJson('/api/v1/notifications')->json('meta.total'))->toBe(1);
});

it('shows every type with in-app on and email off until I change it', function () {
    $prefs = $this->actingAs($this->user, 'web')->getJson('/api/v1/me/notification-preferences')->assertOk()->json('data');

    expect(array_column($prefs, 'type'))->toBe(array_map(fn ($t) => $t->value, NotificationType::cases()))
        ->and(collect($prefs)->every(fn ($p) => $p['in_app'] === true && $p['email'] === false))->toBeTrue();
});

it('follows my preferences: nothing when a type is off, email when I opt in', function () {
    $this->actingAs($this->user, 'web')->putJson('/api/v1/me/notification-preferences', ['preferences' => [
        ['type' => 'lesson_completed', 'in_app' => false, 'email' => false],
        ['type' => 'assessment_result', 'in_app' => true, 'email' => true],
    ]])->assertOk()
        ->assertJsonPath('data.1', ['type' => 'lesson_completed', 'in_app' => false, 'email' => false])
        ->assertJsonPath('data.4', ['type' => 'assessment_result', 'in_app' => true, 'email' => true])
        ->assertJsonPath('data.0', ['type' => 'recommendation', 'in_app' => true, 'email' => false]);

    Notification::fake();
    passLesson($this->user, 'linux-logs');
    submitBlankAssessment($this->user);

    Notification::assertNotSentTo($this->user, LearningNotification::class, fn ($n) => $n->kind === NotificationType::LessonCompleted);
    Notification::assertSentTo($this->user, LearningNotification::class, fn ($n, $channels) => $n->kind === NotificationType::AssessmentResult && $channels === ['database', 'mail']);
});

it('refuses invalid preferences', function (array $body) {
    $this->actingAs($this->user, 'web')->putJson('/api/v1/me/notification-preferences', $body)->assertUnprocessable();
})->with([
    'nothing' => [[]],
    'unknown type' => [['preferences' => [['type' => 'spam', 'in_app' => true, 'email' => true]]]],
    'missing channel' => [['preferences' => [['type' => 'reminder', 'in_app' => true]]]],
    'not a boolean' => [['preferences' => [['type' => 'reminder', 'in_app' => 'sometimes', 'email' => false]]]],
    'same type twice' => [['preferences' => [['type' => 'reminder', 'in_app' => true, 'email' => true], ['type' => 'reminder', 'in_app' => false, 'email' => false]]]],
]);

it('removes my notifications with my account', function () {
    passLesson($this->user, 'linux-logs');
    $this->user->delete();

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('sends nothing to a suspended account', function () {
    $this->user->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Test'])->save();
    submitBlankAssessment($this->user);

    expect(stored($this->user))->toBe([]);
});

it('writes emails in the account language with a link into the app', function () {
    $notification = new LearningNotification(NotificationType::Milestone, ['kind' => 'streak', 'days' => 7], '/dashboard');

    $en = $notification->toMail($this->user);
    expect($en->subject)->toBe('7 days in a row')
        ->and($en->actionUrl)->toBe(rtrim(config('app.frontend_url'), '/').'/dashboard');

    App::setLocale('vi');
    $vi = $notification->toMail($this->user);
    App::setLocale('en');
    expect($vi->subject)->toBe('7 ngày liên tiếp')
        ->and($vi->greeting)->toBe('Chào Lan,');
});

it('never lets another learner act on my notification through the guard cache', function () {
    passLesson($this->user, 'linux-logs');
    $id = $this->user->notifications()->value('id');
    $stranger = User::factory()->create();

    Auth::forgetGuards();
    $this->actingAs($stranger, 'web')->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
    expect($this->user->unreadNotifications()->count())->toBe(1);
});
