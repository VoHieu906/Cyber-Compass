<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Enums\ThemePreference;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'locale' => Locale::English,
            'theme' => ThemePreference::System,
        ];
    }
}
