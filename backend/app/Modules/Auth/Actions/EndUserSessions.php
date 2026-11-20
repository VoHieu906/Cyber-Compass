<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a user out everywhere: deletes their database sessions and rotates the
 * remember-me token, so an existing "remember me" cookie stops working too.
 * Used after a password reset and by admin role changes and suspensions.
 */
final class EndUserSessions
{
    public function handle(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
    }
}
