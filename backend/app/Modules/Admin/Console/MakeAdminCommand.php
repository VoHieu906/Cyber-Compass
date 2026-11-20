<?php

namespace App\Modules\Admin\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Auth\Actions\EndUserSessions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Promotes an existing account to admin from the server: the first admin of a fresh
 * deployment, and recovery if every admin is lost. On AWS it runs as a one-off ECS task.
 * It never creates accounts or touches passwords; the person registers normally first.
 */
final class MakeAdminCommand extends Command
{
    protected $signature = 'cybercompass:make-admin {email : Email of an existing account}';

    protected $description = 'Promote an existing account to admin (also lifts a suspension)';

    public function handle(AuditLogger $audit, EndUserSessions $endSessions): int
    {
        $user = User::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();

        if (! $user) {
            $this->error('No account uses that email. Register it first, then run this again.');

            return self::FAILURE;
        }

        if ($user->isAdmin() && ! $user->isSuspended()) {
            $this->info("{$user->email} is already an active admin.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user, $audit, $endSessions) {
            $changes = [];
            if (! $user->isAdmin()) {
                $changes['role'] = [$user->role->value, UserRole::Admin->value];
            }
            if ($user->isSuspended()) {
                $changes['suspended'] = [true, false];
            }

            $user->forceFill(['role' => UserRole::Admin, 'suspended_at' => null, 'suspension_reason' => null])->save();
            $endSessions->handle($user);
            $audit->record(null, 'user.promoted_from_cli', 'user', $user->id, $changes);
        });

        $this->info("{$user->email} is now an admin. They need to sign in again.");

        return self::SUCCESS;
    }
}
