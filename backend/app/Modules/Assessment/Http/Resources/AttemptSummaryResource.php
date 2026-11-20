<?php

namespace App\Modules\Assessment\Http\Resources;

use App\Enums\ScoreBand;
use App\Models\AssessmentAttempt;
use App\Modules\Assessment\Services\AttemptResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A submitted attempt in the learner's history: overall and per-domain scores, no questions.
 *
 * @mixin AssessmentAttempt
 */
final class AttemptSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $scores = app(AttemptResult::class)->scores($this->resource);

        return [
            'id' => $this->id,
            'assessment' => ['slug' => $this->assessment->slug, 'title' => $this->assessment->title],
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'score' => $this->score_percent,
            'band' => ScoreBand::forPercent((int) $this->score_percent)->value,
            'domains' => collect($scores['domains'])
                ->map(fn (array $row, string $slug) => ['slug' => $slug, 'score' => $row['score'], 'band' => $row['band']->value])
                ->values()
                ->all(),
        ];
    }
}
