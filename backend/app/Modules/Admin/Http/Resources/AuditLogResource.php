<?php

namespace App\Modules\Admin\Http\Resources;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the actor relation loaded and, optionally, a subject_label attribute
 * (set by AuditLogQuery) naming the subject for display.
 *
 * @mixin AdminAuditLog
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor' => $this->actor ? ['id' => $this->actor->id, 'name' => $this->actor->name] : null,
            'subject' => [
                'type' => $this->subject_type,
                'id' => $this->subject_id,
                'label' => $this->resource->getAttributes()['subject_label'] ?? null,
            ],
            'changes' => $this->changes ?? (object) [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
