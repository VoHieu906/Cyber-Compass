<?php

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseGraph;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

function runE2eUsers(array $options): array
{
    Artisan::call('cybercompass:e2e-users', $options);

    return json_decode(trim(Artisan::output()), true);
}

it('creates throwaway learners and admins that can sign in', function () {
    $out = runE2eUsers(['--learners' => 2, '--admins' => 1]);

    expect($out['learners'])->toHaveCount(2)
        ->and($out['admins'])->toHaveCount(1);
    foreach ([...$out['learners'], ...$out['admins']] as $email) {
        expect($email)->toMatch('/^e2e-[a-z]+-[0-9]{14}-[a-z0-9]{4}-\d@example\.test$/');
        $user = User::query()->where('email', $email)->firstOrFail();
        expect(Hash::check($out['password'], $user->password))->toBeTrue()
            ->and($user->profile)->not->toBeNull();
    }
    expect(User::query()->where('email', $out['admins'][0])->first()->role)->toBe(UserRole::Admin)
        ->and(User::query()->where('email', $out['learners'][0])->first()->role)->toBe(UserRole::Learner);

    $this->postJson('/api/v1/auth/login', ['email' => $out['learners'][0], 'password' => $out['password']])->assertOk();
});

it('purges only e2e accounts on example.test', function () {
    runE2eUsers(['--learners' => 1]);
    User::factory()->create(['email' => 'e2e-learner-from-the-ui@example.test']);
    $keep = [
        User::factory()->create(['email' => 'someone@example.test']),
        User::factory()->create(['email' => 'e2e-lookalike@example.com']),
        User::factory()->create(['email' => 'not-e2e-@example.test']),
    ];

    $e2ePost = Post::query()->create(['slug' => 'e2e-post', 'title' => 'E2E', 'tags' => [], 'created_by' => User::query()->where('email', 'e2e-learner-from-the-ui@example.test')->value('id')]);
    $realPost = Post::query()->create(['slug' => 'real-post', 'title' => 'Real', 'tags' => [], 'created_by' => $keep[0]->id]);

    expect(runE2eUsers(['--purge' => true]))->toBe(['deleted' => 2]);

    expect(User::query()->where('email', 'like', 'e2e-%@example.test')->count())->toBe(0);
    foreach ($keep as $user) {
        expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
    }
    // Blog posts the suite made go too; everyone else's stay.
    expect(Post::query()->whereKey($e2ePost->id)->exists())->toBeFalse()
        ->and(Post::query()->whereKey($realPost->id)->exists())->toBeTrue();
});

it('purges the course maps the suite saved and the lessons it named e2e-*, nothing else (F4 Part C)', function () {
    $suite = User::factory()->admin()->create(['email' => 'e2e-admin-x@example.test']);
    $real = User::factory()->admin()->create(['email' => 'editor@example.test']);
    $graph = ['version' => 1, 'problem' => 'P.', 'layout' => 'auto', 'edges' => []];
    $courses = Course::query()->where('slug', '!=', 'networking-fundamentals')->orderBy('id')->limit(2)->get();
    $suiteMap = CourseGraph::query()->create(['course_id' => $courses[0]->id, 'graph' => $graph, 'updated_by' => $suite->id]);
    $realMap = CourseGraph::query()->create(['course_id' => $courses[1]->id, 'graph' => $graph, 'updated_by' => $real->id]);
    $chapter = $courses[0]->chapters()->firstOrFail();
    $lesson = tap($chapter->lessons()->firstOrFail()->replicate()->forceFill(['slug' => 'e2e-lesson-1', 'position' => 99]))->save();

    runE2eUsers(['--purge' => true]);

    expect(CourseGraph::query()->whereKey($suiteMap->id)->exists())->toBeFalse()
        ->and(CourseGraph::query()->whereKey($realMap->id)->exists())->toBeTrue()
        ->and(CourseGraph::query()->whereHas('course', fn ($q) => $q->where('slug', 'networking-fundamentals'))->exists())->toBeTrue()
        ->and(Lesson::query()->whereKey($lesson->id)->exists())->toBeFalse()
        ->and(Lesson::query()->where('slug', 'osi-model')->exists())->toBeTrue();
});

it('refuses outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('cybercompass:e2e-users', ['--learners' => 1])
        ->expectsOutputToContain('only in local and testing')
        ->assertFailed();
    $this->artisan('cybercompass:e2e-users', ['--purge' => true])->assertFailed();

    expect(User::query()->where('email', 'like', 'e2e-%')->count())->toBe(0);
});

it('rejects bad counts', function () {
    $this->artisan('cybercompass:e2e-users', ['--learners' => 'x'])->assertFailed();
    $this->artisan('cybercompass:e2e-users', ['--admins' => 21])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
