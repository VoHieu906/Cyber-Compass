<?php

namespace App\Modules\Roadmap\Services;

use App\Models\Domain;
use App\Models\Topic;
use App\Models\User;
use App\Modules\Assessment\Services\AttemptResult;
use Illuminate\Support\Collection;

/**
 * Every topic a learner can study, grouped by domain, with the learner's status, plus each
 * domain's latest assessment score and lesson completion. Works without a career goal or
 * roadmap. Topics with no learner-visible lesson are left out, as in the generator (§6.2).
 *
 * On the map a topic is available when every prerequisite topic on the map is completed:
 * there is no plan here, so "review" does not open anything.
 */
final class KnowledgeMap
{
    public function __construct(
        private readonly TopicStatus $topics,
        private readonly AttemptResult $results,
    ) {}

    /** @return array<string, mixed> */
    public function present(User $user): array
    {
        $topics = Topic::query()
            ->with(['domain:id,slug,name,position', 'prerequisites:id,slug', 'lessons' => TopicStatus::lessonsConstraint()])
            ->get()
            ->filter(fn (Topic $t) => $t->lessons->isNotEmpty())
            ->sortBy(fn (Topic $t) => [$t->domain->position, $t->level, $t->id])
            ->values();
        $progress = $this->topics->progress($topics, $user);
        $inPath = $user->learningPaths()->active()->first()?->nodes()->pluck('topic_id')->all() ?? [];

        $rows = $topics->map(function (Topic $topic) use ($progress, $inPath) {
            $p = $progress[$topic->slug];
            $requires = $topic->prerequisites->pluck('slug')->filter(fn ($slug) => $progress->has($slug))->values();

            return [
                'slug' => $topic->slug,
                'title' => $topic->title,
                'summary' => $topic->summary,
                'level' => $topic->level,
                'domain' => $topic->domain->slug,
                'status' => TopicStatus::status($p, $requires->every(fn ($slug) => $progress[$slug]['completed'])),
                'percent' => $p['percent'],
                'in_path' => in_array($topic->id, $inPath, true),
                'requires' => $requires->all(),
                'lessons' => $p['lessons']->all(),
            ];
        });

        return [
            'domains' => $this->domains($user, $topics, $progress),
            'topics' => $rows->all(),
            'edges' => $rows->flatMap(fn ($row) => array_map(fn ($from) => ['from' => $from, 'to' => $row['slug']], $row['requires']))->values()->all(),
        ];
    }

    /**
     * Per domain: the latest submitted assessment's score and band, and completion over the
     * required lessons of the domain's topics. Phase 6 `/progress/skills` reuses this.
     *
     * @param  Collection<int, Topic>  $topics
     * @param  Collection<string, array<string, mixed>>  $progress  from TopicStatus::progress
     * @return list<array<string, mixed>>
     */
    public function domains(User $user, Collection $topics, Collection $progress): array
    {
        $attempt = $user->assessmentAttempts()->submitted()->latest('submitted_at')->latest('id')->first();
        $scores = $attempt ? $this->results->scores($attempt)['domains'] : [];

        return Domain::query()->orderBy('position')->get(['id', 'slug', 'name'])
            ->map(function (Domain $domain) use ($topics, $progress, $scores) {
                $lessons = $topics->where('domain_id', $domain->id)
                    ->flatMap(fn (Topic $t) => $progress[$t->slug]['required'])
                    ->unique('slug');
                $done = $lessons->where('status', 'completed')->count();
                $score = $scores[$domain->slug] ?? null;

                return [
                    'slug' => $domain->slug,
                    'name' => $domain->name,
                    'score' => $score['score'] ?? null,
                    'band' => $score ? $score['band']->value : null,
                    'completion' => [
                        'completed' => $done,
                        'total' => $lessons->count(),
                        'percent' => $lessons->isEmpty() ? 0 : (int) round($done / $lessons->count() * 100),
                    ],
                ];
            })->values()->all();
    }
}
