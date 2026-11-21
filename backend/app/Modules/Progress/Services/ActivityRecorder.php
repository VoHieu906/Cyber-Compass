<?php

namespace App\Modules\Progress\Services;

use App\Enums\ActivityType;
use App\Models\LearningActivity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The only writer of `learning_activities` (D33), used by the event listener and the one-off
 * backfill. `once()` makes the backfill safe to run again.
 */
final class ActivityRecorder
{
    public function record(int $userId, ActivityType $type, Model $subject, CarbonInterface $at, int $seconds = 0): LearningActivity
    {
        return LearningActivity::query()->create($this->row($userId, $type, $subject, $at, $seconds));
    }

    /**
     * Records the activity unless the learner already has one of this type for this subject.
     * Used by the backfill for things that happen once per subject (starting or first
     * completing a lesson, completing a lab, submitting a given attempt), so rows the listener
     * already wrote are never duplicated.
     */
    public function once(int $userId, ActivityType $type, Model $subject, CarbonInterface $at, int $seconds = 0): bool
    {
        $row = $this->row($userId, $type, $subject, $at, $seconds);
        $exists = LearningActivity::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('subject_type', $row['subject_type'])
            ->where('subject_id', $row['subject_id'])
            ->exists();
        if (! $exists) {
            LearningActivity::query()->create($row);
        }

        return ! $exists;
    }

    /** @return array<string, mixed> */
    private function row(int $userId, ActivityType $type, Model $subject, CarbonInterface $at, int $seconds): array
    {
        return [
            'user_id' => $userId,
            'type' => $type,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'duration_seconds' => max(0, $seconds),
            'occurred_at' => $at->copy()->startOfSecond(),
        ];
    }
}
