<?php

use App\Enums\ScoreBand;
use App\Modules\Assessment\Services\ScoringService;

function item(string $domain, int $difficulty, bool $correct, bool $answered = true): array
{
    return compact('domain', 'difficulty', 'correct', 'answered');
}

it('weights each question by its difficulty', function () {
    $result = (new ScoringService)->score([
        item('networking', 1, true),
        item('networking', 3, false),
    ]);

    // 1 of 4 weighted points.
    expect($result['domains']['networking'])->toMatchArray([
        'score' => 25, 'earned' => 1, 'possible' => 4, 'correct' => 1, 'total' => 2, 'band' => ScoreBand::Gap,
    ]);
});

it('counts unanswered questions as incorrect but reports them as unanswered', function () {
    $result = (new ScoringService)->score([
        item('linux', 2, true),
        item('linux', 2, false, answered: false),
    ]);

    expect($result['domains']['linux'])->toMatchArray(['score' => 50, 'answered' => 1, 'total' => 2]);
});

it('scores the overall result with the same formula across every question, not as an average of domains', function () {
    $result = (new ScoringService)->score([
        item('networking', 3, true),   // networking 100%
        item('linux', 1, false),       // linux 0%
    ]);

    // Average of domains would be 50; weighted overall is 3 of 4.
    expect($result['overall']['score'])->toBe(75)
        ->and($result['overall']['band'])->toBe(ScoreBand::Strength);
});

it('places scores in bands at 40 and 75, using the rounded percentage', function (int $percent, ScoreBand $band) {
    expect(ScoreBand::forPercent($percent))->toBe($band);
})->with([
    [0, ScoreBand::Gap],
    [39, ScoreBand::Gap],
    [40, ScoreBand::Developing],
    [74, ScoreBand::Developing],
    [75, ScoreBand::Strength],
    [100, ScoreBand::Strength],
]);

it('rounds to whole percents', function () {
    // 2 of 3 weighted points = 66.67%.
    $result = (new ScoringService)->score([item('crypto', 1, true), item('crypto', 1, true), item('crypto', 1, false)]);

    expect($result['domains']['crypto']['score'])->toBe(67);
});

it('scores nothing as zero', function () {
    $result = (new ScoringService)->score([]);

    expect($result['overall'])->toMatchArray(['score' => 0, 'possible' => 0, 'band' => ScoreBand::Gap])
        ->and($result['domains'])->toBe([]);
});
