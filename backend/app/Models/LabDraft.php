<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Unpublished edits to a lab: a validated snapshot of its whole definition (D74). Holds answers; admin routes only. */
#[Fillable(['lab_id', 'snapshot', 'lock_version', 'updated_by'])]
class LabDraft extends Model
{
    /** @return BelongsTo<Lab, $this> */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'lock_version' => 'integer'];
    }
}
