<?php

namespace App\Modules\Assessment\Services;

use App\Enums\ScoreBand;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Domain;
use Illuminate\Support\Collection;

/**
 * The result of a submitted attempt (ARCHITECTURE §6.1, D26), computed from its stored answers.
 *
 * Every domain score shows its counts so the learner can redo the arithmetic. The review gives
 * the correct option and the explanation only for questions answered wrongly or left unanswered;
 * a correct answer is only marked as correct.
 */
final class AttemptResult
{
    /** How many "study this domain" steps the result recommends. */
    public const MAX_COURSE_STEPS = 3;

    public function __construct(private readonly ScoringService $scoring) {}

    /** @return array<string, mixed> */
    public function build(AssessmentAttempt $attempt): array
    {
        $attempt->loadMissing(['assessment', 'answers.question.domain', 'answers.question.options', 'answers.option']);
        $scores = $this->scores($attempt);
        $domains = Domain::query()->orderBy('position')->get()->keyBy('slug');

        $domainRows = collect($scores['domains'])
            ->sortBy(fn ($row, string $slug) => $domains[$slug]->position)
            ->map(fn (array $row, string $slug) => ['domain' => ['slug' => $slug, 'name' => $domains[$slug]->name]] + self::row($row))
            ->values();

        return [
            'id' => $attempt->id,
            'assessment' => ['slug' => $attempt->assessment->slug, 'title' => $attempt->assessment->title],
            'started_at' => $attempt->started_at->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'overall' => self::row($scores['overall']),
            'domains' => $domainRows->all(),
            'strengths' => $domainRows->where('band', ScoreBand::Strength->value)->pluck('domain.slug')->values()->all(),
            'gaps' => $domainRows->where('band', ScoreBand::Gap->value)->pluck('domain.slug')->values()->all(),
            'next_steps' => $this->nextSteps($domainRows),
            'formula' => [
                'weights' => ['1' => 1, '2' => 2, '3' => 3],
                'unanswered' => 'incorrect',
                'developing_from' => ScoreBand::DEVELOPING_FROM,
                'strength_from' => ScoreBand::STRENGTH_FROM,
            ],
            'review' => $attempt->answers->map(fn (AssessmentAnswer $answer) => $this->review($answer))->all(),
        ];
    }

    /**
     * Overall and per-domain scores from the stored answers (keyed by domain slug).
     *
     * @return array{overall: array<string, mixed>, domains: array<string, array<string, mixed>>}
     */
    public function scores(AssessmentAttempt $attempt): array
    {
        $attempt->loadMissing('answers.question.domain');

        return $this->scoring->score($attempt->answers->map(fn (AssessmentAnswer $answer) => [
            'domain' => $answer->question->domain->slug,
            'difficulty' => $answer->question->difficulty,
            'correct' => (bool) $answer->is_correct,
            'answered' => $answer->assessment_option_id !== null,
        ]));
    }

    /**
     * @param  array{score: int, band: ScoreBand, earned: int, possible: int, correct: int, answered: int, total: int}  $row
     * @return array<string, mixed>
     */
    private static function row(array $row): array
    {
        return ['band' => $row['band']->value] + $row;
    }

    /**
     * Rule-based next steps: the introductory course of each gap domain, then of each developing
     * domain, weakest first. Strength domains need no course.
     *
     * @param  Collection<int, array<string, mixed>>  $domainRows
     * @return list<array<string, mixed>>
     */
    private function nextSteps(Collection $domainRows): array
    {
        $weak = $domainRows
            ->whereIn('band', [ScoreBand::Gap->value, ScoreBand::Developing->value])
            ->sortBy(fn (array $row) => [$row['band'] === ScoreBand::Gap->value ? 0 : 1, $row['score']])
            ->values();

        $courses = Course::query()
            ->published()
            ->whereHas('domain', fn ($q) => $q->whereIn('slug', $weak->pluck('domain.slug')))
            ->with('domain:id,slug')
            ->orderBy('difficulty')
            ->orderBy('id')
            ->get()
            ->unique('domain_id')
            ->keyBy('domain.slug');

        return $weak
            ->filter(fn (array $row) => $courses->has($row['domain']['slug']))
            ->take(self::MAX_COURSE_STEPS)
            ->map(fn (array $row) => [
                'kind' => 'course',
                'reason' => $row['band'],
                'domain' => $row['domain'],
                'score' => $row['score'],
                'course' => ['slug' => $courses[$row['domain']['slug']]->slug, 'title' => $courses[$row['domain']['slug']]->title],
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function review(AssessmentAnswer $answer): array
    {
        $question = $answer->question;
        $correct = (bool) $answer->is_correct;
        $key = $correct ? null : $question->options->firstWhere('is_correct', true);

        return [
            'question_id' => $question->id,
            'position' => $answer->position,
            'domain' => $question->domain->slug,
            'difficulty' => $question->difficulty,
            'prompt' => $question->prompt,
            'code' => $question->code,
            'is_correct' => $correct,
            'your_option' => $answer->option ? ['id' => $answer->option->id, 'label' => $answer->option->label] : null,
            // Revealed only for wrong or unanswered questions (D26).
            'correct_option' => $key ? ['id' => $key->id, 'label' => $key->label] : null,
            'explanation' => $correct ? null : $question->explanation,
        ];
    }
}
