<?php

namespace App\Modules\Notifications\Services;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Which channels each notification type uses for a learner (D34). Only changed types are
 * stored; everything else follows the defaults: in the app on, email off.
 */
final class NotificationPreferences
{
    public const DEFAULT = ['in_app' => true, 'email' => false];

    /** @return list<array{type: string, in_app: bool, email: bool}> */
    public function all(User $user): array
    {
        $stored = NotificationPreference::query()->where('user_id', $user->id)->get()->keyBy(fn (NotificationPreference $p) => $p->type->value);

        return array_map(fn (NotificationType $type) => [
            'type' => $type->value,
            'in_app' => $stored[$type->value]->in_app ?? self::DEFAULT['in_app'],
            'email' => $stored[$type->value]->email ?? self::DEFAULT['email'],
        ], NotificationType::cases());
    }

    /**
     * Laravel channel names for one type: `database` for in the app, `mail` for email.
     *
     * @return list<string>
     */
    public function channels(User $user, NotificationType $type): array
    {
        $row = NotificationPreference::query()->where('user_id', $user->id)->where('type', $type)->first();
        $inApp = $row->in_app ?? self::DEFAULT['in_app'];
        $email = $row->email ?? self::DEFAULT['email'];

        return array_values(array_filter([$inApp ? 'database' : null, $email ? 'mail' : null]));
    }

    /** @param  list<array{type: string, in_app: bool, email: bool}>  $rows */
    public function update(User $user, array $rows): void
    {
        DB::transaction(function () use ($user, $rows) {
            foreach ($rows as $row) {
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->id, 'type' => $row['type']],
                    ['in_app' => (bool) $row['in_app'], 'email' => (bool) $row['email']],
                );
            }
        });
    }
}
