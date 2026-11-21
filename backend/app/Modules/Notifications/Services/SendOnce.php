<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Claims a one-off notification key for a learner, such as `streak:7:2026-11-15` or
 * `reminder:2026-11-21`. The unique index makes the claim atomic, so two workers can never
 * both send it, and deleting the notification never makes it come back (D34).
 */
final class SendOnce
{
    public function claim(User $user, string $key): bool
    {
        return DB::table('notification_sends')->insertOrIgnore(['user_id' => $user->id, 'key' => $key, 'created_at' => now()]) === 1;
    }
}
