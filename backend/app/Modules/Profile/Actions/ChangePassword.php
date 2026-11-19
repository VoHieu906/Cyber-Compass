<?php

namespace App\Modules\Profile\Actions;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ChangePassword
{
    /**
     * Updates the password and signs out every other session. The current
     * session continues with a fresh id.
     */
    public function handle(Request $request, User $user, string $password): void
    {
        DB::transaction(function () use ($request, $user, $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        });

        $request->session()->regenerate();
    }
}
