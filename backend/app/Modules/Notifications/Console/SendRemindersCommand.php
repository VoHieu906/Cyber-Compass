<?php

namespace App\Modules\Notifications\Console;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Notifications\Notifications\LearningNotification;
use App\Modules\Notifications\Services\NotificationPreferences;
use App\Modules\Notifications\Services\SendOnce;
use App\Modules\Progress\Services\LearnerClock;
use App\Modules\Progress\Services\StreakCalculator;
use Illuminate\Console\Command;

/**
 * Hourly (routes/console.php): reminds learners with a learning goal who have not studied
 * today, when it is the reminder hour (18:00) in their own timezone (D33, D34). At most one
 * reminder per local day; nothing for suspended accounts or learners who switched reminders off.
 */
final class SendRemindersCommand extends Command
{
    public const HOUR = 18;

    protected $signature = 'notifications:send-reminders';

    protected $description = 'Remind learners who have not studied today, at 18:00 their time';

    public function handle(LearnerClock $clock, StreakCalculator $streaks, NotificationPreferences $preferences, SendOnce $once): int
    {
        $sent = 0;

        User::query()
            ->where('role', UserRole::Learner)
            ->whereNull('suspended_at')
            ->whereHas('learningGoal')
            ->with('profile:id,user_id,timezone,locale')
            ->chunkById(200, function ($users) use ($clock, $streaks, $preferences, $once, &$sent) {
                foreach ($users as $user) {
                    $now = $clock->nowIn(LearnerClock::zone($user->profile?->timezone));
                    if ($now->hour !== self::HOUR || $preferences->channels($user, NotificationType::Reminder) === []) {
                        continue;
                    }
                    if ($user->learningActivities()->where('occurred_at', '>=', $now->startOfDay()->utc())->exists()) {
                        continue;
                    }
                    if (! $once->claim($user, 'reminder:'.$now->toDateString())) {
                        continue;
                    }
                    $streak = $streaks->for($user)['current'];
                    $user->notify(new LearningNotification(NotificationType::Reminder, ['streak' => $streak], '/dashboard'));
                    $sent++;
                }
            });

        $this->info("Sent {$sent} reminders.");

        return self::SUCCESS;
    }
}
