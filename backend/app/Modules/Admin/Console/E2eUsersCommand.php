<?php

namespace App\Modules\Admin\Console;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Auth\Actions\RegisterUser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Throwaway accounts for the Playwright suite (D45). Accounts are made through the same
 * registration action as the sign-up form, so they start exactly like a new learner, and the
 *
 * register rate limit stays a real limit. Every account matches `e2e-*@example.test`, and
 * --purge deletes only those. Refuses to run outside local and testing environments.
 *
 * Prints one JSON line: {"password": "...", "learners": [...], "admins": [...]}.
 */
final class E2eUsersCommand extends Command
{
    public const DOMAIN = 'example.test';

    public const PREFIX = 'e2e-';

    protected $signature = 'cybercompass:e2e-users
        {--learners=1 : Learner accounts to create}
        {--admins=0 : Admin accounts to create}
        {--purge : Delete every e2e-*@example.test account instead}';

    protected $description = 'Create or purge throwaway accounts for end-to-end tests (local and testing only)';

    public function handle(RegisterUser $register): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('cybercompass:e2e-users runs only in local and testing environments.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            $deleted = 0;
            self::throwaway()->each(function (User $user) use (&$deleted) {
                $user->delete();
                $deleted++;
            });
            $this->line(json_encode(['deleted' => $deleted]));

            return self::SUCCESS;
        }

        $learners = $this->count('learners');
        $admins = $this->count('admins');
        if ($learners === null || $admins === null) {
            return self::FAILURE;
        }

        $password = Str::password(20, symbols: false);
        $run = now()->format('YmdHis').'-'.Str::lower(Str::random(4));
        $make = function (string $kind, int $i) use ($register, $password, $run): User {
            return $register->handle([
                'name' => 'E2E '.ucfirst($kind).' '.($i + 1),
                'email' => self::PREFIX."{$kind}-{$run}-{$i}@".self::DOMAIN,
                'password' => $password,
            ], Locale::English);
        };

        $out = ['password' => $password, 'learners' => [], 'admins' => []];
        for ($i = 0; $i < $learners; $i++) {
            $out['learners'][] = $make('learner', $i)->email;
        }
        for ($i = 0; $i < $admins; $i++) {
            $user = $make('admin', $i);
            $user->forceFill(['role' => UserRole::Admin])->save();
            $out['admins'][] = $user->email;
        }

        $this->line(json_encode($out));

        return self::SUCCESS;
    }

    /** Accounts this command owns: e2e-*@example.test, the UI-registered ones included. */
    public static function throwaway()
    {
        return User::query()->where('email', 'like', self::PREFIX.'%@'.self::DOMAIN);
    }

    private function count(string $option): ?int
    {
        $value = $this->option($option);
        if (! ctype_digit((string) $value) || (int) $value > 20) {
            $this->error("--{$option} must be a whole number from 0 to 20.");

            return null;
        }

        return (int) $value;
    }
}
