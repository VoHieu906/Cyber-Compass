<?php

namespace App\Modules\Roadmap\Services;

use App\Models\LearningPath;
use App\Models\LearningPathNode;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A roadmap version with the learner's status, computed on read and never stored
 * (ARCHITECTURE §6.3, D4). Status comes from `TopicStatus`; on the roadmap a topic is
 * available when every prerequisite topic in the path is completed or marked review.
 * Prerequisites outside the path are not part of the plan and are ignored.
 */
final class PathProgress
{
    public function __construct(private readonly TopicStatus $topics) {}

    /** @return array<string, mixed> */
    public function present(LearningPath $path, User $user): array
    {
        $path->loadMissing([
            'career:id,slug,title',
            'attempt:id,score_percent,submitted_at',
            'nodes.topic.domain:id,slug,name',
            'nodes.topic.prerequisites:id,slug',
            'nodes.topic.lessons' => TopicStatus::lessonsConstraint(),
        ]);

        // First pass: completion per topic (independent of other topics).
        $completion = $this->topics->progress($path->nodes->map(fn (LearningPathNode $n) => $n->topic), $user);
        $nodes = $path->nodes->mapWithKeys(fn (LearningPathNode $node) => [$node->topic->slug => ['node' => $node] + $completion[$node->topic->slug]]);

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
                'status' => TopicStatus::status($n, $open),
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
        $next = $this->nextLessons($rows, 1)[0] ?? null;

        return $next ? ['slug' => $next['slug'], 'title' => $next['title'], 'topic' => $next['topic']] : null;
    }

    /**
     * Up to `$limit` unfinished lessons in roadmap order, by the same rule as `nextLesson()`:
     * open required topics first, then open review topics. Used by `/progress/next`.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{slug: string, title: string, topic: string, estimated_minutes: int}>
     */
    public function nextLessons(Collection $rows, int $limit): array
    {
        $found = [];
        foreach (['required', 'review'] as $recommendation) {
            foreach ($rows as $row) {
                if ($row['recommendation'] !== $recommendation || ! in_array($row['status'], ['in_progress', 'available'], true)) {
                    continue;
                }
                foreach ($row['lessons'] as $lesson) {
                    if ($lesson['status'] === 'completed' || isset($found[$lesson['slug']])) {
                        continue;
                    }
                    $found[$lesson['slug']] = ['slug' => $lesson['slug'], 'title' => $lesson['title'], 'topic' => $row['topic']['title'], 'estimated_minutes' => (int) $lesson['estimated_minutes']];
                    if (count($found) >= $limit) {
                        return array_values($found);
                    }
                }
            }
        }

        return array_values($found);
    }
}
