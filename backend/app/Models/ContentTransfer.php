<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A content package upload or export (F6, D70), owned by one admin. The file is on the transfers
 * disk under `path`; `report` holds the preview (import) or what was exported.
 */
#[Fillable(['user_id', 'direction', 'status', 'file_name', 'size_bytes', 'path', 'upload_id', 'parts', 'subject', 'report', 'error', 'expires_at'])]
class ContentTransfer extends Model
{
    public const UPLOADING = 'uploading';

    public const VALIDATING = 'validating';

    public const READY = 'ready';

    public const APPLYING = 'applying';

    public const APPLIED = 'applied';

    public const EXPORTING = 'exporting';

    public const FAILED = 'failed';

    /** Mirrors the column defaults, so strict mode never meets a missing attribute on a new model. */
    protected $attributes = [
        'size_bytes' => null, 'path' => null, 'upload_id' => null, 'parts' => null,
        'subject' => null, 'report' => null, 'error' => null,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expired(): bool
    {
        return $this->expires_at->isPast();
    }

    protected function casts(): array
    {
        return ['subject' => 'array', 'report' => 'array', 'expires_at' => 'datetime', 'size_bytes' => 'integer', 'parts' => 'integer'];
    }
}
