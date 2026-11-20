<?php

namespace App\Modules\Assessment\Services;

use App\Enums\ScoreBand;

/**
 * The published scoring formula (ARCHITECTURE §6.1). Pure: no database access, so it is
 * unit-tested directly and reused by the roadmap generator and, in Phase 6, the dashboard.
 *
 *   score = Σ weight(correct) / Σ weight(all questions) × 100, rounded to a whole percent
 *   weight = difficulty (1, 2 or 3)
 *
 * Unanswered questions count as incorrect. The overall score uses the same formula across
 * every question, so it is not an average of the domain scores.
 */
final class ScoringService
{
    /**
     * @param  iterable<array{domain: string, difficulty: int, correct: bool, answered: bool}>  $items
     * @return array{
     *     overall: array{score: int, band: ScoreBand, earned: int, possible: int, correct: int, answered: int, total: int},
     *     domains: array<string, array{score: int, band: ScoreBand, earned: int, possible: int, correct: int, answered: int, total: int}>
     * }
     */
    public function score(iterable $items): array
    {
        $overall = self::empty();
        $domains = [];

        foreach ($items as $item) {
            $domains[$item['domain']] ??= self::empty();
            foreach ([&$overall, &$domains[$item['domain']]] as &$tally) {
                $tally['possible'] += $item['difficulty'];
                $tally['total']++;
                if ($item['answered']) {
                    $tally['answered']++;
                }
                if ($item['correct']) {
                    $tally['earned'] += $item['difficulty'];
                    $tally['correct']++;
                }
            }
            unset($tally);
        }

        return [
            'overall' => self::finish($overall),
            'domains' => array_map(self::finish(...), $domains),
        ];
    }

    /** @return array{earned: int, possible: int, correct: int, answered: int, total: int} */
    private static function empty(): array
    {
        return ['earned' => 0, 'possible' => 0, 'correct' => 0, 'answered' => 0, 'total' => 0];
    }

    /**
     * @param  array{earned: int, possible: int, correct: int, answered: int, total: int}  $tally
     * @return array{score: int, band: ScoreBand, earned: int, possible: int, correct: int, answered: int, total: int}
     */
    private static function finish(array $tally): array
    {
        $score = $tally['possible'] > 0 ? (int) round($tally['earned'] / $tally['possible'] * 100) : 0;

        return ['score' => $score, 'band' => ScoreBand::forPercent($score)] + $tally;
    }
}
