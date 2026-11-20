<?php

namespace App\Modules\Roadmap\Services;

use App\Enums\LessonProgressStatus;
use App\Models\LearningPath;
use App\Models\LearningPathNode;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A roadmap version with the learner's status, computed on read and never stored
 * (ARCHITECTURE §6.3, D4):
 *  - completed: every required lesson of the topic is completed
 *  - in_progress: at least one lesson started or completed
 *  - available: every prerequisite topic in the path is completed or marked review
 *  - locked: otherwise
 *
 * Only lessons a learner can see count. A topic whose lessons were all archived after the path
 * was generated has nothing left to do, so it counts as completed and never blocks the next.
 */
final class PathProgress
{
    /** @return array<string, mixed> */
    public function present(LearningPath $path, User $user): array
    {
        $path->loadMissing([
            'career:id,slug,title',
            'attempt:id,score_percent,submitted_at',
            'nodes.topic.domain:id,slug,name',
            'nodes.topic.prerequisites:id,slug',
            'nodes.topic.lessons' => fn ($q) => $q->visibleToLearners()->select(['lessons.id', 'lessons.slug', 'lessons.title', 'lessons.estimated_minutes']),
        ]);
        $lessonIds = $path->nodes->flatMap(fn (LearningPathNode $n) => $n->topic->lessons->pluck('id'))->unique();
        $progress = $user->lessonProgress()->whereIn('lesson_id', $lessonIds)->get()->keyBy('lesson_id');
        $lessonStatus = fn (Lesson $l) => match ($progress[$l->id]->status ?? null) {
            LessonProgressStatus::Completed => 'completed',
            LessonProgressStatus::Started => 'started',
            default => 'not_started',
        };

        // First pass: completion per topic (independent of other topics).
        $nodes = $path->nodes->map(function (LearningPathNode $node) use ($lessonStatus) {
            $lessons = $node->topic->lessons->map(fn (Lesson $l) => [
                'slug' => $l->slug,
                'title' => $l->title,
                'estimated_minutes' => $l->estimated_minutes,
                'is_required' => (bool) $l->pivot->is_required,
                'status' => $lessonStatus($l),
            ]);
            $required = $lessons->where('is_required', true);
            $required = $required->isEmpty() ? $lessons : $required;
            $done = $required->where('status', 'completed')->count();

            return [
                'node' => $node,
                'lessons' => $lessons,
                'required' => $required,
                'completed' => $done === $required->count(),
                'started' => $lessons->contains(fn ($l) => $l['status'] !== 'not_started'),
                'percent' => $required->isEmpty() ? 100 : (int) round($done / $required->count() * 100),
            ];
        })->keyBy(fn ($n) => $n['node']->topic->slug);

        // Second pass: availability from the prerequisites that are part of this path.
        $rows = $nodes->map(function (array $n) use ($nodes) {
            $node = $n['node'];
            $prerequisites = $node->topic->prerequisites->pluck('slug')->filter(fn ($slug) => $nodes->has($slug))->values();
            $open = $prerequisites->every(fn ($slug) => $nodes[$slug]['completed'] || $nodes[$slug]['node']->recommendation === 'review');

            return [
                'topic' => [
                    'slug' => $node->topic->slug,
                    'title' => $node->topic->title,
                    'summary' => $node->topic->summary,
                    'level' => $node->topic->level,
                    'domain' => ['slug' => $node->topic->domain->slug, 'name' => $node->topic->domain->name],
                ],
                'stage' => $node->stage,
                'position' => $node->position,
                'recommendation' => $node->recommendation,
                'reason' => ['key' => $node->reason_key, 'params' => $node->reason_params],
                'status' => match (true) {
                    $n['completed'] => 'completed',
                    $n['started'] => 'in_progress',
                    $open => 'available',
                    default => 'locked',
                },
                'percent' => $n['percent'],
                'requires' => $prerequisites->all(),
                'lessons' => $n['lessons']->values()->all(),
            ];
        })->values();

        $counted = $nodes->filter(fn ($n) => $n['node']->recommendation === 'required');
        $requiredLessons = $counted->flatMap(fn ($n) => $n['required']);
        $remaining = $requiredLessons->where('status', '!=', 'completed');
        $goal = $user->learningGoal()->first();
        $weekly = $goal?->weekly_minutes;
        $latestAttemptId = $user->assessmentAttempts()->submitted()->latest('submitted_at')->latest('id')->value('id');

        return [
            'id' => $path->id,
            'version' => $path->version,
            'status' => $path->status,
            'generated_at' => $path->generated_at->toIso8601String(),
            'career' => ['slug' => $path->career->slug, 'title' => $path->career->title],
            'assessment' => $path->attempt
                ? ['attempt_id' => $path->attempt->id, 'score' => $path->attempt->score_percent, 'submitted_at' => $path->attempt->submitted_at?->toIso8601String()]
                : null,
            'completion_percent' => $requiredLessons->isEmpty() ? 0 : (int) round(($requiredLessons->count() - $remaining->count()) / $requiredLessons->count() * 100),
            'estimate' => [
                'remaining_minutes' => (int) $remaining->sum('estimated_minutes'),
                'weekly_minutes' => $weekly,
                'weeks' => $weekly ? (int) ceil($remaining->sum('estimated_minutes') / $weekly) : null,
            ],
            'next_lesson' => $this->nextLesson($rows),
            'outdated' => array_values(array_filter([
                $goal?->career_id !== null && $goal->career_id !== $path->career_id ? 'career_changed' : null,
                $latestAttemptId !== null && $latestAttemptId !== $path->assessment_attempt_id ? 'new_assessment' : null,
            ])),
            'nodes' => $rows->all(),
            'edges' => $rows->flatMap(fn ($row) => array_map(fn ($from) => ['from' => $from, 'to' => $row['topic']['slug']], $row['requires']))->values()->all(),
        ];
    }

    /**
     * The first unfinished lesson of the first open required topic, else of the first open
     * review topic. Reused by the dashboard's "next lessons" in Phase 6.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{slug: string, title: string, topic: string}|null
     */
    public function nextLesson(Collection $rows): ?array
    {
        foreach (['required', 'review'] as $recommendation) {
            foreach ($rows as $row) {
                if ($row['recommendation'] !== $recommendation || ! in_array($row['status'], ['in_progress', 'available'], true)) {
                    continue;
                }
                foreach ($row['lessons'] as $lesson) {
                    if ($lesson['status'] !== 'completed') {
                        return ['slug' => $lesson['slug'], 'title' => $lesson['title'], 'topic' => $row['topic']['title']];
                    }
                }
            }
        }

        return null;
    }
}
