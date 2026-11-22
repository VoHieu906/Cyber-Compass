<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'secure-pass-1']);
});

it('requires a session for every profile endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['PATCH', '/api/v1/me/profile'],
    ['POST', '/api/v1/me/avatar'],
    ['DELETE', '/api/v1/me/avatar'],
    ['PATCH', '/api/v1/me/preferences'],
    ['PUT', '/api/v1/me/password'],
]);

describe('personal information', function () {
    it('updates name, phone, date of birth and education level', function () {
        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/profile', [
                'name' => '  Minh Tran  ',
                'phone' => '+84 912 345 678',
                'date_of_birth' => '2003-05-14',
                'education_level' => 'undergraduate',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Minh Tran')
            ->assertJsonPath('data.profile.phone', '+84 912 345 678')
            ->assertJsonPath('data.profile.date_of_birth', '2003-05-14')
            ->assertJsonPath('data.profile.education_level', 'undergraduate');
    });

    it('clears optional fields when sent empty', function () {
        $this->user->profile->update(['phone' => '0912345678']);

        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/profile', ['name' => 'Minh', 'phone' => ''])
            ->assertOk()
            ->assertJsonPath('data.profile.phone', null);
    });

    it('validates the fields', function (array $input, string $field) {
        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/profile', ['name' => 'Minh', ...$input])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'phone with letters' => [['phone' => 'call me'], 'phone'],
        'birth date in the future' => [['date_of_birth' => now()->addDay()->format('Y-m-d')], 'date_of_birth'],
        'unknown education level' => [['education_level' => 'phd'], 'education_level'],
        'empty name' => [['name' => ''], 'name'],
    ]);

    it('cannot change the email or role through the profile', function () {
        $email = $this->user->email;

        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/profile', ['name' => 'Minh', 'email' => 'new@example.com', 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.email', $email)
            ->assertJsonPath('data.role', 'learner');
    });
});

describe('preferences', function () {
    it('saves language and theme', function () {
        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/preferences', ['locale' => 'vi', 'theme' => 'dark'])
            ->assertOk()
            ->assertJsonPath('data.profile.locale', 'vi')
            ->assertJsonPath('data.profile.theme', 'dark');
    });

    it('accepts a single preference', function () {
        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/preferences', ['theme' => 'light'])
            ->assertOk()
            ->assertJsonPath('data.profile.theme', 'light')
            ->assertJsonPath('data.profile.locale', 'en');
    });

    it('rejects unsupported values', function () {
        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/preferences', ['locale' => 'fr', 'theme' => 'neon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale', 'theme']);
    });

    it('defaults to the compass on and no shortcut overrides', function () {
        $this->actingAs($this->user, 'web')->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile.compass_enabled', true)
            ->assertJsonPath('data.profile.shortcuts', []);
    });

    it('saves the compass switch and shortcut overrides', function () {
        $shortcuts = [
            'compass.toggle' => ['code' => 'KeyJ', 'alt' => true, 'shift' => true],
            'search.open' => ['key' => '/'],
            'search.openCombo' => null,
        ];
        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/preferences', ['compass_enabled' => false, 'shortcuts' => $shortcuts])
            ->assertOk()
            ->assertJsonPath('data.profile.compass_enabled', false)
            ->assertJsonPath('data.profile.shortcuts', fn (array $saved) => $saved['compass.toggle']['code'] === 'KeyJ');

        $profile = $this->user->profile()->first();
        expect($profile->compass_enabled)->toBeFalse()
            ->and($profile->shortcuts)->toEqual($shortcuts);

        // An empty map resets every shortcut and leaves the other preferences alone.
        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/preferences', ['shortcuts' => (object) []])
            ->assertOk()
            ->assertJsonPath('data.profile.compass_enabled', false);
        expect($profile->fresh()->shortcuts)->toBe([]);
    });

    it('rejects malformed shortcut maps', function (mixed $shortcuts) {
        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/preferences', ['shortcuts' => $shortcuts])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shortcuts']);
    })->with([
        'not a map' => [[['code' => 'KeyK']]],
        'bad action id' => [['Bad Id!' => ['code' => 'KeyK']]],
        'code and key' => [['search.open' => ['code' => 'KeyK', 'key' => 'k']]],
        'neither code nor key' => [['search.open' => ['alt' => true]]],
        'unknown field' => [['search.open' => ['code' => 'KeyK', 'script' => 'x']]],
        'non-boolean modifier' => [['search.open' => ['code' => 'KeyK', 'alt' => 'yes']]],
        'long key' => [['search.open' => ['key' => 'ab']]],
        'bad code' => [['search.open' => ['code' => '<b>']]],
        'too many' => [collect(range(1, 65))->mapWithKeys(fn ($i) => ["a{$i}" => null])->all()],
    ]);

    it('rejects a non-boolean compass switch', function () {
        $this->actingAs($this->user, 'web')
            ->patchJson('/api/v1/me/preferences', ['compass_enabled' => 'sometimes'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['compass_enabled']);
    });
});

describe('password', function () {
    it('changes the password and signs out other sessions', function () {
        DB::table('sessions')->insert([
            'id' => 'other-device', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($this->user, 'web')
            ->putJson('/api/v1/me/password', [
                'current_password' => 'secure-pass-1',
                'password' => 'brand-new-2',
                'password_confirmation' => 'brand-new-2',
            ])
            ->assertNoContent();

        expect(Hash::check('brand-new-2', $this->user->fresh()->password))->toBeTrue();
        expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
    });

    it('requires the correct current password', function () {
        $this->actingAs($this->user, 'web')
            ->putJson('/api/v1/me/password', [
                'current_password' => 'wrong-pass-1',
                'password' => 'brand-new-2',
                'password_confirmation' => 'brand-new-2',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
    });

    it('requires the new password to differ from the current one', function () {
        $this->actingAs($this->user, 'web')
            ->putJson('/api/v1/me/password', [
                'current_password' => 'secure-pass-1',
                'password' => 'secure-pass-1',
                'password_confirmation' => 'secure-pass-1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    });
});

describe('avatar', function () {
    beforeEach(fn () => Storage::fake(config('cybercompass.uploads_disk')));

    it('stores the upload as a 256px square WebP under a random name', function () {
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('Me & My Cat.png', 800, 600)])
            ->assertOk();

        $path = $this->user->profile->fresh()->avatar_path;
        $disk = Storage::disk(config('cybercompass.uploads_disk'));

        expect($path)->toStartWith("avatars/{$this->user->id}/")->toEndWith('.webp')->not->toContain('Cat');
        $disk->assertExists($path);
        expect(getimagesizefromstring($disk->get($path)))->toMatchArray([0 => 256, 1 => 256, 'mime' => 'image/webp']);
        expect($response->json('data.profile.avatar_url'))->toContain($path);
    });

    it('replaces and deletes the previous avatar', function () {
        $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
        $first = $this->user->profile->fresh()->avatar_path;

        $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('b.jpg', 300, 300)]);

        Storage::disk(config('cybercompass.uploads_disk'))->assertMissing($first);
    });

    it('rejects files that are not raster images', function (UploadedFile $file) {
        $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/me/avatar', ['avatar' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    })->with([
        'svg' => fn () => UploadedFile::fake()->createWithContent('a.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'pdf' => fn () => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        'too small' => fn () => UploadedFile::fake()->image('tiny.png', 20, 20),
        'too large' => fn () => UploadedFile::fake()->image('big.jpg', 500, 500)->size(3000),
    ]);

    it('removes the avatar', function () {
        $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
        $path = $this->user->profile->fresh()->avatar_path;

        $this->actingAs($this->user, 'web')
            ->deleteJson('/api/v1/me/avatar')
            ->assertOk()
            ->assertJsonPath('data.profile.avatar_url', null);

        Storage::disk(config('cybercompass.uploads_disk'))->assertMissing($path);
    });
});
