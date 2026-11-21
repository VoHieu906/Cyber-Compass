<?php

namespace App\Modules\Progress\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Learning streaks in the learner's local dates (D33). A day counts when it has any activity.
 * Today does not break a streak until it ends: with nothing yet today, the streak that ran
 * through yesterday is still current.
 */
final class StreakCalculator
{
    public function __construct(private readonly LearnerClock $clock) {}

    /** @return array{current: int, longest: int, active_today: bool, last_active_on: ?string} */
    public function for(User $user): array
    {
        $timezone = $this->clock->timezone($user);
        $days = $user->learningActivities()
            ->orderBy('occurred_at')
            ->pluck('occurred_at')
            ->map(fn ($at) => LearnerClock::localDate($at, $timezone))
            ->unique()
            ->values()
            ->all();

        return self::fromDays($days, $this->clock->now($user)->toDateString());
    }

    /**
     * The pure rule, on local `Y-m-d` dates.
     *
     * @param  list<string>  $days
     * @return array{current: int, longest: int, active_today: bool, last_active_on: ?string}
     */
    public static function fromDays(array $days, string $today): array
    {
        $set = array_flip($days);
        $sorted = array_keys($set);
        sort($sorted);

        $longest = 0;
        $run = 0;
        $previous = null;
        foreach ($sorted as $day) {
            $run = $previous !== null && self::shift($previous, 1) === $day ? $run + 1 : 1;
            $longest = max($longest, $run);
            $previous = $day;
        }

        $activeToday = isset($set[$today]);
        $current = 0;
        for ($day = $activeToday ? $today : self::shift($today, -1); isset($set[$day]); $day = self::shift($day, -1)) {
            $current++;
        }

        return [
            'current' => $current,
            'longest' => $longest,
            'active_today' => $activeToday,
            'last_active_on' => $sorted === [] ? null : end($sorted),
        ];
    }

    private static function shift(string $day, int $days): string
    {
        return CarbonImmutable::parse($day, 'UTC')->addDays($days)->toDateString();
    }
}
