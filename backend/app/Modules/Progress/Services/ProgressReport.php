<?php

namespace App\Modules\Progress\Services;

use App\Enums\ActivityType;
use App\Enums\LessonProgressStatus;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\ExerciseAttempt;
use App\Models\Lab;
use App\Models\LearningActivity;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Catalog\Services\CourseProgress;
use App\Modules\Roadmap\Services\KnowledgeMap;
use App\Modules\Roadmap\Services\PathProgress;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * The learner dashboard's numbers (Phase 6, D33). Activity figures come from
 * `learning_activities`; roadmap completion and next lessons from `PathProgress`; skills from
 * `KnowledgeMap`; course completion from `CourseProgress`. Nothing is recomputed here.
 */
final class ProgressReport
{
    public const MAX_WEEKS = 52;

    public const RECENT_LIMIT = 15;

    public function __construct(
        private readonly LearnerClock $clock,
        private readonly StreakCalculator $streaks,
        private readonly PathProgress $paths,
        private readonly KnowledgeMap $map,
        private readonly CourseProgress $courses,
    ) {}

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        $now = $this->clock->now($user);
        $weekStart = $now->startOfWeek(CarbonInterface::MONDAY);
        $path = $user->learningPaths()->active()->first();

        return [
            'enrolled_courses' => $user->enrollments()->count(),
            'completed_courses' => $user->enrollments()->whereNotNull('completed_at')->count(),
            'completed_lessons' => $user->lessonProgress()
                ->where('status', LessonProgressStatus::Completed)
                ->whereHas('lesson', fn ($q) => $q->visibleToLearners())
                ->count(),
            'total_minutes' => intdiv((int) $user->learningActivities()->sum('duration_seconds'), 60),
            'week' => [
                'starts_on' => $weekStart->toDateString(),
                'minutes' => intdiv((int) $user->learningActivities()->where('occurred_at', '>=', $weekStart->utc())->sum('duration_seconds'), 60),
                'goal_minutes' => $user->learningGoal()->value('weekly_minutes'),
            ],
            'streak' => $this->streaks->for($user),
            'roadmap' => $path ? [
                'career' => $path->career()->value('title'),
                'completion_percent' => $this->paths->present($path, $user)['completion_percent'],
            ] : null,
            'timezone' => $this->clock->timezone($user),
        ];
    }

    /**
     * Minutes studied and lessons completed per local week, oldest first, for the last `$weeks`
     * weeks including this one. `lessons_completed_before` lets a chart draw the running total.
     *
     * @return array<string, mixed>
     */
    public function weekly(User $user, int $weeks): array
    {
        $timezone = $this->clock->timezone($user);
        $first = $this->clock->now($user)->startOfWeek(CarbonInterface::MONDAY)->subWeeks($weeks - 1);

        $rows = [];
        for ($i = 0; $i < $weeks; $i++) {
            $rows[$first->addWeeks($i)->toDateString()] = ['seconds' => 0, 'lessons' => 0];
        }

        $user->learningActivities()
            ->where('occurred_at', '>=', $first->utc())
            ->get(['type', 'duration_seconds', 'occurred_at'])
            ->each(function (LearningActivity $activity) use (&$rows, $timezone) {
                $week = CarbonImmutable::instance($activity->occurred_at)->setTimezone($timezone)->startOfWeek(CarbonInterface::MONDAY)->toDateString();
                if (! isset($rows[$week])) {
                    return;
                }
                $rows[$week]['seconds'] += $activity->duration_seconds;
                $rows[$week]['lessons'] += $activity->type === ActivityType::LessonCompleted ? 1 : 0;
            });

        return [
            'timezone' => $timezone,
            'lessons_completed_before' => $user->learningActivities()
                ->where('type', ActivityType::LessonCompleted)
                ->where('occurred_at', '<', $first->utc())
                ->count(),
            'weeks' => collect($rows)->map(fn (array $row, string $start) => [
                'starts_on' => $start,
                'minutes' => intdiv($row['seconds'], 60),
                'lessons_completed' => $row['lessons'],
            ])->values()->all(),
        ];
    }

    /**
     * Opening a lesson is left out of the feed: it would double every completion and every
     * lesson glanced at. Starts still count for streaks.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(User $user): array
    {
        return $user->learningActivities()
            ->where('type', '!=', ActivityType::LessonStarted)
            ->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([
                ExerciseAttempt::class => ['exercise.lesson:id,slug,title'],
                AssessmentAttempt::class => ['assessment:id,slug,title'],
            ])])
            ->latest('occurred_at')
            ->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (LearningActivity $a) => [
                'id' => $a->id,
                'type' => $a->type->value,
                'occurred_at' => $a->occurred_at->toIso8601String(),
                'minutes' => intdiv($a->duration_seconds, 60),
                'subject' => self::subject($a->subject),
            ])
            ->all();
    }

    /**
     * Up to three lessons to study next: from the active roadmap when there is one, otherwise
     * the next lesson of each unfinished enrolled course.
     *
     * @return array{source: ?string, lessons: list<array<string, mixed>>}
     */
    public function next(User $user, int $limit = 3): array
    {
        $path = $user->learningPaths()->active()->first();
        if ($path) {
            return ['source' => 'roadmap', 'lessons' => $this->paths->nextLessons(collect($this->paths->present($path, $user)['nodes']), $limit)];
        }

        $lessons = [];
        foreach ($this->enrollments($user)->whereNull('completed_at') as $enrollment) {
            $next = $this->courses->for($user, $enrollment->course)['next_lesson'];
            if ($next) {
                $minutes = Lesson::query()->where('slug', $next['slug'])->value('estimated_minutes');
                $lessons[] = $next + ['course' => $enrollment->course->title, 'estimated_minutes' => (int) $minutes];
            }
            if (count($lessons) >= $limit) {
                break;
            }
        }

        return ['source' => $lessons === [] ? null : 'courses', 'lessons' => $lessons];
    }

    /** @return list<array<string, mixed>> */
    public function skills(User $user): array
    {
        return $this->map->skills($user);
    }

    /** Completion of every enrolled course, unfinished courses first. @return list<array<string, mixed>> */
    public function courses(User $user): array
    {
        return $this->enrollments($user)
            ->sortBy(fn (Enrollment $e) => [$e->completed_at !== null, -$e->enrolled_at->getTimestamp()])
            ->map(function (Enrollment $e) use ($user) {
                $progress = $this->courses->for($user, $e->course);

                return [
                    'slug' => $e->course->slug,
                    'title' => $e->course->title,
                    'domain' => $e->course->domain ? ['slug' => $e->course->domain->slug, 'name' => $e->course->domain->name] : null,
                    'enrolled_at' => $e->enrolled_at->toIso8601String(),
                    'completed_at' => $e->completed_at?->toIso8601String(),
                    'completed_lessons' => $progress['completed_lessons'],
                    'total_lessons' => $progress['total_lessons'],
                    'percent' => $progress['percent'],
                ];
            })
            ->values()
            ->all();
    }

    /** @return Collection<int, Enrollment> */
    private function enrollments(User $user): Collection
    {
        return $user->enrollments()
            ->with('course:id,slug,title,domain_id', 'course.domain:id,slug,name')
            ->orderByDesc('enrolled_at')
            ->get();
    }

    /** @return array<string, mixed>|null */
    private static function subject(mixed $subject): ?array
    {
        return match (true) {
            $subject instanceof Lesson => ['kind' => 'lesson', 'slug' => $subject->slug, 'title' => $subject->title],
            $subject instanceof Lab => ['kind' => 'lab', 'slug' => $subject->slug, 'title' => $subject->title],
            $subject instanceof ExerciseAttempt => [
                'kind' => 'exercise',
                'lesson' => ['slug' => $subject->exercise->lesson->slug, 'title' => $subject->exercise->lesson->title],
                'score' => $subject->score_percent,
                'passed' => (bool) $subject->passed,
            ],
            $subject instanceof AssessmentAttempt => [
                'kind' => 'assessment',
                'id' => $subject->id,
                'title' => $subject->assessment->title,
                'score' => $subject->score_percent,
            ],
            default => null,
        };
    }
}
