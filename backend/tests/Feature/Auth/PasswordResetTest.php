<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create(['email' => 'learner@example.com', 'password' => 'old-pass-1']);
});

it('emails a reset link that points at the frontend', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'learner@example.com'])
        ->assertAccepted();

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) {
        $url = $notification->toMail($this->user)->actionUrl;

        return str_starts_with($url, config('app.frontend_url').'/reset-password?token=')
            && str_contains($url, 'email=learner%40example.com');
    });
});

it('gives the same answer for an unknown email and sends nothing', function () {
    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'learner@example.com'])->json();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertAccepted()
        ->json();

    expect($unknown)->toBe($known);
    Notification::assertSentTimes(ResetPassword::class, 1);
});

it('resets the password with a valid token and ends every session', function () {
    $token = Password::createToken($this->user);
    DB::table('sessions')->insert([
        'id' => 'old-session', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time(),
    ]);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'learner@example.com',
        'password' => 'new-pass-22',
        'password_confirmation' => 'new-pass-22',
    ])->assertOk();

    expect(Hash::check('new-pass-22', $this->user->fresh()->password))->toBeTrue();
    expect(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0);
});

it('rejects an invalid token', function () {
    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'forged',
        'email' => 'learner@example.com',
        'password' => 'new-pass-22',
        'password_confirmation' => 'new-pass-22',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(Hash::check('old-pass-1', $this->user->fresh()->password))->toBeTrue();
});

it('has a Vietnamese translation for every line of the reset email', function () {
    app()->setLocale('vi');

    $mail = (new ResetPassword('token'))->toMail($this->user);

    expect($mail->subject)->toBe('Đặt lại mật khẩu CyberCompass')
        ->and($mail->actionText)->toBe('Đặt lại mật khẩu')
        ->and(implode(' ', [...$mail->introLines, ...$mail->outroLines]))->not->toContain('password');
});

it('sends the reset email in the user\'s language', function () {
    $this->user->profile->update(['locale' => 'vi']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'learner@example.com']);

    Notification::assertSentTo($this->user, ResetPassword::class, function ($notification, $channels, $notifiable, $locale) {
        return $locale === 'vi';
    });
});
