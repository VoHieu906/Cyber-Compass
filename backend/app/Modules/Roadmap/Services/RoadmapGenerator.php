<?php

namespace App\Modules\Roadmap\Services;

use App\Enums\ScoreBand;
use App\Models\Career;
use App\Models\Topic;
use Illuminate\Support\Collection;

/**
 * Turns a career's roadmap template and the learner's domain scores into an ordered list of
 * nodes (ARCHITECTURE §6.2). Rule-based and deterministic; every node carries the reason it is
 * there, so the learner can see why.
 *
 * For each topic of the template, in order:
 *  - domain score at least 75 and topic level at most 2: `review` (optional), reason `strength`
 *  - domain score below 40: `required`, reason `gap`; when the topic is above the domain's lowest
 *    level, the domain's lowest-level topics that are missing are inserted just before it
 *    (with any missing prerequisites first), reason `foundation`
 *  - otherwise: `required`, reason `career`
 *
 * Without scores (no submitted assessment) every topic is `required`. Topics with no lesson a
 * learner can see are left out, and an inserted topic is never placed twice.
 */
final class RoadmapGenerator
{
    /** Topics at or below this level can be marked for review when their domain is a strength. */
    public const REVIEW_MAX_LEVEL = 2;

    /** @var array<int, true> */
    private array $placed = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $stages = [];

    /** @var Collection<int, Topic> */
    private Collection $topics;

    /**
     * @param  array<string, int>|null  $domainScores  domain slug => score percent, or null without an assessment
     * @return list<array{topic_id: int, stage: int, position: int, recommendation: string, reason_key: string, reason_params: array<string, mixed>}>
     */
    public function plan(Career $career, ?array $domainScores): array
    {
        $this->placed = [];
        $this->stages = [];
        $this->topics = Topic::query()
            ->with(['domain:id,slug,name', 'prerequisites:id'])
            ->withCount(['lessons as visible_lessons_count' => fn ($q) => $q->visibleToLearners()])
            ->orderBy('level')
            ->orderBy('id')
            ->get()
            ->keyBy('id');
        $lowestLevel = $this->topics->groupBy('domain_id')->map(fn (Collection $group) => $group->min('level'));

        $template = $career->topics()->get(['topics.id']);
        foreach ($template as $entry) {
            $topic = $this->topics[$entry->id];
            $stage = (int) $entry->pivot->stage;
            $domain = $topic->domain;
            $score = $domainScores[$domain->slug] ?? null;

            if ($score !== null && $score < ScoreBand::DEVELOPING_FROM) {
                if ($topic->level > $lowestLevel[$topic->domain_id]) {
                    $foundations = $this->topics->filter(fn (Topic $t) => $t->domain_id === $topic->domain_id && $t->level === $lowestLevel[$topic->domain_id]);
                    foreach ($foundations as $foundation) {
                        $this->placeWithPrerequisites($foundation, $stage, ['topic' => $topic->title, 'domain' => $domain->name]);
                    }
                }
                $this->place($topic, $stage, 'required', 'gap', ['domain' => $domain->name, 'score' => $score]);
            } elseif ($score !== null && $score >= ScoreBand::STRENGTH_FROM && $topic->level <= self::REVIEW_MAX_LEVEL) {
                $this->place($topic, $stage, 'review', 'strength', ['domain' => $domain->name, 'score' => $score]);
            } else {
                $this->place($topic, $stage, 'required', 'career', ['career' => $career->title]);
            }
        }

        ksort($this->stages);
        $nodes = [];
        foreach ($this->stages as $stage => $stageNodes) {
            foreach ($stageNodes as $i => $node) {
                $nodes[] = ['stage' => $stage, 'position' => $i + 1] + $node;
            }
        }

        return $nodes;
    }

    /** @param  array<string, mixed>  $params */
    private function placeWithPrerequisites(Topic $topic, int $stage, array $params): void
    {
        if (isset($this->placed[$topic->id])) {
            return;
        }
        foreach ($topic->prerequisites->sortBy('id') as $prerequisite) {
            $this->placeWithPrerequisites($this->topics[$prerequisite->id], $stage, $params);
        }
        $this->place($topic, $stage, 'required', 'foundation', $params);
    }

    /** @param  array<string, mixed>  $params */
    private function place(Topic $topic, int $stage, string $recommendation, string $reasonKey, array $params): void
    {
        if (isset($this->placed[$topic->id]) || $topic->visible_lessons_count === 0) {
            return;
        }
        $this->placed[$topic->id] = true;
        $this->stages[$stage][] = [
            'topic_id' => $topic->id,
            'recommendation' => $recommendation,
            'reason_key' => $reasonKey,
            'reason_params' => $params,
        ];
    }
}
