<?php

use App\Enums\ActivityType;
use App\Models\LearningActivity;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Content\Events\LessonPublished;
use App\Modules\Notifications\Jobs\RecommendNewLesson;
use App\Modules\Notifications\Services\NotificationPreferences;
use App\Modules\Profile\Actions\SaveLearningGoal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

/** A learner with a goal in the given timezone. */
function learnerIn(?string $timezone, bool $goal = true): User
{
    $user = User::factory()->create();
    $user->profile()->updateOrCreate([], ['timezone' => $timezone]);
    if ($goal) {
        app(SaveLearningGoal::class)->handle($user, ['career' => 'soc-analyst', 'weekly_minutes' => 120]);
    }

    return $user;
}

function reminders(User $user): int
{
    return $user->notifications()->where('type', 'reminder')->count();
}

describe('reminders', function () {
    beforeEach(function () {
        // 11:30 UTC is 18:30 in Ho Chi Minh City (UTC+7).
        $this->travelTo(CarbonImmutable::parse('2026-11-21 11:30', 'UTC'));
    });

    it('reminds learners with a goal at 18:00 their time when they have not studied today, once a day', function () {
        $due = learnerIn('Asia/Ho_Chi_Minh');
        $elsewhere = learnerIn('Europe/Paris');
        $utc = learnerIn(null);
        $noGoal = learnerIn('Asia/Ho_Chi_Minh', goal: false);
        $studied = learnerIn('Asia/Ho_Chi_Minh');
        LearningActivity::query()->create(['user_id' => $studied->id, 'type' => ActivityType::StudySession, 'subject_type' => 'lesson', 'subject_id' => Lesson::query()->value('id'), 'duration_seconds' => 60, 'occurred_at' => now()->subHours(2)]);

        Artisan::call('notifications:send-reminders');
        Artisan::call('notifications:send-reminders');

        expect([reminders($due), reminders($elsewhere), reminders($utc), reminders($noGoal), reminders($studied)])->toBe([1, 0, 0, 0, 0]);
        expect($due->notifications()->value('data'))->toEqual(['params' => ['streak' => 0], 'url' => '/dashboard']);
    });

    it('treats yesterday\'s study as yesterday in the learner\'s timezone', function () {
        $due = learnerIn('Asia/Ho_Chi_Minh');
        // 16:00 UTC yesterday is 23:00 yesterday in Ho Chi Minh City.
        LearningActivity::query()->create(['user_id' => $due->id, 'type' => ActivityType::StudySession, 'subject_type' => 'lesson', 'subject_id' => Lesson::query()->value('id'), 'duration_seconds' => 60, 'occurred_at' => CarbonImmutable::parse('2026-11-20 16:00', 'UTC')]);

        Artisan::call('notifications:send-reminders');

        expect($due->notifications()->value('data')['params'])->toEqual(['streak' => 1]);
    });

    it('skips learners who switched reminders off and suspended accounts', function () {
        $off = learnerIn('Asia/Ho_Chi_Minh');
        app(NotificationPreferences::class)->update($off, [['type' => 'reminder', 'in_app' => false, 'email' => false]]);
        $suspended = learnerIn('Asia/Ho_Chi_Minh');
        $suspended->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Test'])->save();

        Artisan::call('notifications:send-reminders');

        expect([reminders($off), reminders($suspended)])->toBe([0, 0]);
    });

    it('is scheduled every hour', function () {
        Artisan::call('schedule:list');

        expect(Artisan::output())->toContain('0 * * * *')->toContain('notifications:send-reminders');
    });
});

describe('recommendations', function () {
    it('recommends a newly published lesson to learners on its course or roadmap topic, once, unless done', function () {
        $lesson = Lesson::query()->where('slug', 'defense-in-depth')->with('chapter.course')->firstOrFail();
        [$enrolled, $done, $stranger] = [User::factory()->create(), User::factory()->create(), User::factory()->create()];
        foreach ([$enrolled, $done] as $user) {
            $this->actingAs($user, 'web')->postJson("/api/v1/courses/{$lesson->chapter->course->slug}/enroll")->assertSuccessful();
            Auth::forgetGuards();
        }
        passLesson($done, $lesson);

        LessonPublished::dispatch($lesson);
        RecommendNewLesson::dispatch($lesson->id);

        expect($enrolled->notifications()->where('type', 'recommendation')->get()->map(fn ($n) => $n->data)->all())->toEqual([[
            'params' => ['lesson' => $lesson->title, 'slug' => 'defense-in-depth', 'context' => $lesson->chapter->course->title, 'via' => 'course'],
            'url' => '/learn/defense-in-depth',
        ]])
            ->and($done->notifications()->where('type', 'recommendation')->count())->toBe(0)
            ->and($stranger->notifications()->count())->toBe(0);
    });

    it('names the roadmap topic for learners whose roadmap holds it', function () {
        $user = learnerIn('UTC');
        $this->actingAs($user, 'web')->postJson('/api/v1/learning-path/generate')->assertCreated();
        $node = $user->learningPaths()->active()->firstOrFail()->nodes()->with('topic.lessons')->first();
        $lesson = $node->topic->lessons->first();

        RecommendNewLesson::dispatch($lesson->id);

        expect($user->notifications()->value('data')['params'])->toMatchArray(['context' => $node->topic->title, 'via' => 'roadmap']);
    });

    it('fires only on a lesson\'s first publish', function () {
        Event::fake([LessonPublished::class]);
        $admin = User::factory()->admin()->create();
        $lesson = Lesson::query()->where('slug', 'hashing-and-passwords')->firstOrFail();
        $data = $this->actingAs($admin, 'web')->getJson("/api/v1/admin/content/lessons/{$lesson->id}")->json('data');
        $this->postJson("/api/v1/admin/content/lessons/{$lesson->id}/publish", ['lock_version' => $data['lock_version']])->assertOk();
        Event::assertNotDispatched(LessonPublished::class);

        $created = $this->postJson('/api/v1/admin/content/lessons', ['chapter_id' => $lesson->course_chapter_id, 'title' => 'Salting in practice', 'slug' => 'salting-in-practice'])->assertCreated()->json('data');
        $saved = $this->putJson("/api/v1/admin/content/lessons/{$created['id']}/draft", [...$created['working_copy'], 'lock_version' => $created['lock_version'],
            'summary' => 'Why every password gets its own salt.',
            'sections' => [['type' => 'prose', 'heading' => 'Why salt', 'payload' => ['text' => 'Rainbow tables stop working.']]],
        ])->assertOk()->json('data');
        $this->postJson("/api/v1/admin/content/lessons/{$created['id']}/publish", ['lock_version' => $saved['lock_version']])->assertOk();

        Event::assertDispatched(LessonPublished::class, fn ($e) => $e->lesson->slug === 'salting-in-practice');
    });
});
