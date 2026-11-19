<?php

namespace App\Modules\Auth\Actions;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

final class RegisterUser
{
    /**
     * Creates the account and its profile atomically. The profile starts in the
     * language the visitor was using when they signed up.
     *
     * @param  array{name: string, email: string, password: string}  $input
     */
    public function handle(array $input, Locale $locale): User
    {
        $user = DB::transaction(function () use ($input, $locale) {
            $user = User::query()->create([
                'name' => $input['name'],
                'email' => mb_strtolower($input['email']),
                'password' => $input['password'],
            ]);

            $user->profile()->create(['locale' => $locale]);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
