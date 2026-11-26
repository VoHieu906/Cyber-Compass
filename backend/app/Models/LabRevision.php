<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One published version of a lab's definition (D74). Holds answers; admin routes only. */
#[Fillable(['lab_id', 'version', 'snapshot', 'note', 'created_by'])]
class LabRevision extends Model
{
    /** @return BelongsTo<Lab, $this> */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer'];
    }
}
