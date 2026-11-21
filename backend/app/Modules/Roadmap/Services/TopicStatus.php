<?php

namespace App\Modules\Roadmap\Services;

use App\Enums\LessonProgressStatus;
use App\Models\Lesson;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The one place topic status is computed (ARCHITECTURE §6.3), shared by the roadmap
 * (`PathProgress`) and the knowledge map:
 *  - completed: every required lesson of the topic is completed (all lessons when none is required)
 *  - in_progress: at least one lesson started or completed
 *  - available: the caller's prerequisite rule is satisfied
 *  - locked: otherwise
 *
 * Topics must have their learner-visible lessons loaded (see `lessonsConstraint()`). A topic
 * with no visible lessons left has nothing to do, so it counts as completed and never blocks.
 */
final class TopicStatus
{
    /** Eager-load constraint for `topic.lessons`: learner-visible lessons with the columns the views need. */
    public static function lessonsConstraint(): \Closure
    {
        return fn ($q) => $q->visibleToLearners()->select(['lessons.id', 'lessons.slug', 'lessons.title', 'lessons.summary', 'lessons.estimated_minutes']);
    }

    /**
     * Completion per topic, independent of other topics, keyed by topic slug.
     *
     * @param  Collection<int, Topic>  $topics
     * @return Collection<string, array{lessons: Collection<int, array<string, mixed>>, required: Collection<int, array<string, mixed>>, completed: bool, started: bool, percent: int}>
     */
    public function progress(Collection $topics, User $user): Collection
    {
        $lessonIds = $topics->flatMap(fn (Topic $t) => $t->lessons->pluck('id'))->unique();
        $progress = $user->lessonProgress()->whereIn('lesson_id', $lessonIds)->get()->keyBy('lesson_id');
        $lessonStatus = fn (Lesson $l) => match ($progress[$l->id]->status ?? null) {
            LessonProgressStatus::Completed => 'completed',
            LessonProgressStatus::Started => 'started',
            default => 'not_started',
        };

        return $topics->mapWithKeys(function (Topic $topic) use ($lessonStatus) {
            $lessons = $topic->lessons->map(fn (Lesson $l) => [
                'slug' => $l->slug,
                'title' => $l->title,
                'summary' => $l->summary,
                'estimated_minutes' => $l->estimated_minutes,
                'is_required' => (bool) $l->pivot->is_required,
                'status' => $lessonStatus($l),
            ])->values();
            $required = $lessons->where('is_required', true);
            $required = $required->isEmpty() ? $lessons : $required;
            $done = $required->where('status', 'completed')->count();

            return [$topic->slug => [
                'lessons' => $lessons,
                'required' => $required,
                'completed' => $done === $required->count(),
                'started' => $lessons->contains(fn ($l) => $l['status'] !== 'not_started'),
                'percent' => $required->isEmpty() ? 100 : (int) round($done / $required->count() * 100),
            ]];
        });
    }

    /** @param  array{completed: bool, started: bool}  $progress */
    public static function status(array $progress, bool $prerequisitesMet): string
    {
        return match (true) {
            $progress['completed'] => 'completed',
            $progress['started'] => 'in_progress',
            $prerequisitesMet => 'available',
            default => 'locked',
        };
    }
}
