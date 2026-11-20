<?php

namespace App\Modules\Admin\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * One user for the admin detail page. Expects enrollments_count, completed_lessons_count,
 * is_last_active_admin and the recent_audit relation to be loaded by the controller.
 *
 * @mixin User
 */
class AdminUserDetailResource extends AdminUserResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'locale' => $this->profile?->locale?->value ?? 'en',
            'education_level' => $this->profile?->education_level?->value,
            'suspension' => $this->isSuspended() ? [
                'at' => $this->suspended_at->toIso8601String(),
                'reason' => $this->suspension_reason,
            ] : null,
            'is_last_active_admin' => (bool) $this->is_last_active_admin,
            'stats' => [
                'enrollments' => (int) $this->enrollments_count,
                'completed_lessons' => (int) $this->completed_lessons_count,
            ],
            'recent_activity' => AuditLogResource::collection($this->recent_audit),
        ];
    }
}
