<?php

namespace App\Modules\Admin\Actions;

use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReactivateUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, User $target): User
    {
        if (! $target->isSuspended()) {
            throw ValidationException::withMessages(['user' => __('This account is not suspended.')]);
        }

        return DB::transaction(function () use ($actor, $target) {
            $reason = $target->suspension_reason;
            $target->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();

            $this->audit->record($actor, 'user.reactivated', 'user', $target->id, ['previous_reason' => $reason]);

            return $target;
        });
    }
}
