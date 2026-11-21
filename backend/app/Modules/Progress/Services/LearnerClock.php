<?php

namespace App\Modules\Progress\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * "Today" and "this week" in the learner's own timezone (D33). The timezone is stored on the
 * profile (filled from the browser); until it is known, or if it is not a valid name, UTC.
 * Weeks start on Monday.
 */
final class LearnerClock
{
    public function timezone(User $user): string
    {
        $timezone = $user->profile()->value('timezone');

        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }

    public function now(User $user): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone($user));
    }

    /** The learner's local calendar date of a stored (UTC) moment. */
    public static function localDate(\DateTimeInterface $moment, string $timezone): string
    {
        return CarbonImmutable::instance($moment)->setTimezone($timezone)->toDateString();
    }
}
