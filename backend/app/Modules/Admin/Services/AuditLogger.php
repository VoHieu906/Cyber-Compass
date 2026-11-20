<?php

namespace App\Modules\Admin\Services;

use App\Models\AdminAuditLog;
use App\Models\User;

/**
 * Writes the append-only admin audit log. Every mutating admin action calls it inside
 * the same transaction as the change, so a change is never recorded without happening
 * or happen without being recorded.
 */
final class AuditLogger
{
    /** Keys whose values never reach the log, at any depth. */
    private const REDACTED = ['password', 'password_confirmation', 'current_password', 'remember_token', 'token', 'secret'];

    /**
     * @param  ?User  $actor  null when the action came from the command line
     * @param  string  $action  e.g. user.role_changed, lesson.published
     * @param  string  $subjectType  user, lesson, chapter
     * @param  array<string, mixed>  $changes  usually ['field' => [from, to]] plus context such as a reason
     */
    public function record(?User $actor, string $action, string $subjectType, ?int $subjectId, array $changes = []): AdminAuditLog
    {
        return AdminAuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes === [] ? null : $this->redact($changes),
            'ip' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip(),
        ]);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
