<?php

use App\Modules\Progress\Services\StreakCalculator;

it('has no streak without activity', function () {
    expect(StreakCalculator::fromDays([], '2026-11-21'))
        ->toBe(['current' => 0, 'longest' => 0, 'active_today' => false, 'last_active_on' => null]);
});

it('counts consecutive days ending today', function () {
    expect(StreakCalculator::fromDays(['2026-11-19', '2026-11-20', '2026-11-21'], '2026-11-21'))
        ->toMatchArray(['current' => 3, 'longest' => 3, 'active_today' => true, 'last_active_on' => '2026-11-21']);
});

it('keeps the streak through yesterday until today ends', function () {
    expect(StreakCalculator::fromDays(['2026-11-19', '2026-11-20'], '2026-11-21'))
        ->toMatchArray(['current' => 2, 'active_today' => false]);
});

it('breaks the streak after a missed day', function () {
    expect(StreakCalculator::fromDays(['2026-11-18', '2026-11-19'], '2026-11-21'))
        ->toMatchArray(['current' => 0, 'longest' => 2]);
});

it('keeps the longest run separately from the current one', function () {
    $days = ['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-10', '2026-11-20', '2026-11-21'];

    expect(StreakCalculator::fromDays($days, '2026-11-21'))->toMatchArray(['current' => 2, 'longest' => 4]);
});

it('runs across month and year ends, in any order, ignoring duplicates', function () {
    $days = ['2027-01-01', '2026-12-31', '2026-12-30', '2026-12-31'];

    expect(StreakCalculator::fromDays($days, '2027-01-01'))->toMatchArray(['current' => 3, 'longest' => 3]);
});
