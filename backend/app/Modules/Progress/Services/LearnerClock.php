<?php

namespace App\Modules\Progress\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * "Today" and "this week" in the learner's own timezone (D33). The timezone is stored on the
 * profile (filled from the browser); until it is known, or if it is not a valid name, UTC.
 * Weeks start on Monday. Legacy aliases such as Asia/Saigon are accepted.
 */
final class LearnerClock
{
    public function timezone(User $user): string
    {
        return self::zone($user->profile()->value('timezone'));
    }

    /** A stored timezone name, or UTC when it is missing or unknown. */
    public static function zone(mixed $timezone): string
    {
        // Browsers may report legacy aliases (Edge says Asia/Saigon for Asia/Ho_Chi_Minh), so those count too.
        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC), true) ? $timezone : 'UTC';
    }

    public function now(User $user): CarbonImmutable
    {
        return $this->nowIn($this->timezone($user));
    }

    public function nowIn(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::now($timezone);
    }

    /** The learner's local calendar date of a stored (UTC) moment. */
    public static function localDate(\DateTimeInterface $moment, string $timezone): string
    {
        return CarbonImmutable::instance($moment)->setTimezone($timezone)->toDateString();
    }
}
