<?php

namespace App\Modules\Profile\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateProfile
{
    /**
     * @param  array{name: string, phone?: ?string, date_of_birth?: ?string, education_level?: ?string}  $input
     */
    public function handle(User $user, array $input): User
    {
        DB::transaction(function () use ($user, $input) {
            $user->update(['name' => $input['name']]);
            $user->profile()->updateOrCreate([], [
                'phone' => $input['phone'] ?? null,
                'date_of_birth' => $input['date_of_birth'] ?? null,
                'education_level' => $input['education_level'] ?? null,
            ]);
        });

        return $user->refresh()->load('profile');
    }
}
