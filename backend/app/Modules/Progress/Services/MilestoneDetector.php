<?php

namespace App\Modules\Progress\Services;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Notifications\Services\SendOnce;
use App\Modules\Progress\Events\MilestoneReached;
use App\Modules\Roadmap\Services\PathProgress;
use Carbon\CarbonImmutable;

/**
 * Finds milestones after learning activity and announces each once (D34). Keys go through
 * `SendOnce`, so a milestone is never repeated, even if its notification is deleted.
 *
 *  - course: the lesson's course is now complete (`course:{id}`)
 *  - roadmap: completion of the active roadmap crossed 25/50/75/100%; keyed by career, so a
 *    regenerated roadmap for the same career does not repeat them; only the highest newly
 *    reached quarter is announced
 *  - streak: today makes a 7, 30 or 100-day streak (`streak:{days}:{first day}`)
 */
final class MilestoneDetector
{
    public const ROADMAP_STEPS = [25, 50, 75, 100];

    public const STREAK_STEPS = [7, 30, 100];

    public function __construct(
        private readonly SendOnce $once,
        private readonly PathProgress $paths,
        private readonly StreakCalculator $streaks,
    ) {}

    public function lessonCompleted(User $user, Lesson $lesson): void
    {
        $lesson->loadMissing('chapter.course:id,slug,title');
        $course = $lesson->chapter->course;
        $done = Enrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->whereNotNull('completed_at')->exists();
        if ($done && $this->once->claim($user, "course:{$course->id}")) {
            MilestoneReached::dispatch($user, ['kind' => 'course', 'title' => $course->title, 'slug' => $course->slug], "/courses/{$course->slug}");
        }

        $path = $user->learningPaths()->active()->first();
        if (! $path) {
            return;
        }
        $percent = $this->paths->present($path, $user)['completion_percent'];
        $reached = null;
        foreach (self::ROADMAP_STEPS as $step) {
            if ($percent >= $step && $this->once->claim($user, "roadmap:{$path->career_id}:{$step}")) {
                $reached = $step;
            }
        }
        if ($reached !== null) {
            MilestoneReached::dispatch($user, ['kind' => 'roadmap', 'percent' => $reached, 'title' => (string) $path->career()->value('title')], '/roadmap');
        }
    }

    public function activity(User $user): void
    {
        $streak = $this->streaks->for($user);
        if (! $streak['active_today'] || ! in_array($streak['current'], self::STREAK_STEPS, true)) {
            return;
        }
        $first = CarbonImmutable::parse($streak['last_active_on'], 'UTC')->subDays($streak['current'] - 1)->toDateString();
        if ($this->once->claim($user, "streak:{$streak['current']}:{$first}")) {
            MilestoneReached::dispatch($user, ['kind' => 'streak', 'days' => $streak['current']], '/dashboard');
        }
    }
}
