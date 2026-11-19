<?php

namespace Database\Seeders;

use App\Enums\Locale;
use App\Enums\ThemePreference;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A demo learner for local development and demonstrations. The password comes from
 * DEMO_USER_PASSWORD in backend/.env and is never committed. Without it, the seeder skips.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('cybercompass.demo_user_password');

        if (blank($password)) {
            $this->command?->warn('DEMO_USER_PASSWORD is not set; skipping the demo user.');

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'demo@cybercompass.test'],
            ['name' => 'Demo Learner', 'password' => $password, 'email_verified_at' => now()],
        );

        $user->profile()->updateOrCreate([], [
            'locale' => Locale::English,
            'theme' => ThemePreference::System,
        ]);
    }
}
