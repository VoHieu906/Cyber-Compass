<?php

namespace Database\Seeders;

use App\Enums\Locale;
use App\Enums\ThemePreference;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for local development and demonstrations. Passwords come from
 * backend/.env and are never committed; an account whose variable is empty is skipped.
 *   demo@cybercompass.test   learner  DEMO_USER_PASSWORD
 *   admin@cybercompass.test  admin    DEMO_ADMIN_PASSWORD
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->account('demo@cybercompass.test', 'Demo Learner', UserRole::Learner, 'demo_user_password', 'DEMO_USER_PASSWORD');
        $this->account('admin@cybercompass.test', 'Demo Admin', UserRole::Admin, 'demo_admin_password', 'DEMO_ADMIN_PASSWORD');
    }

    private function account(string $email, string $name, UserRole $role, string $configKey, string $envName): void
    {
        $password = config("cybercompass.{$configKey}");

        if (blank($password)) {
            $this->command?->warn("{$envName} is not set; skipping {$email}.");

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );
        // Not fillable on purpose; seeders set them directly.
        $user->forceFill(['role' => $role, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $user->profile()->updateOrCreate([], [
            'locale' => Locale::English,
            'theme' => ThemePreference::System,
        ]);
    }
}
