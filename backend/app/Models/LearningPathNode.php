<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One topic in a roadmap version, with why it is there. Its status is computed on read. */
#[Fillable(['learning_path_id', 'topic_id', 'stage', 'position', 'recommendation', 'reason_key', 'reason_params'])]
class LearningPathNode extends Model
{
    /** @return BelongsTo<LearningPath, $this> */
    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }

    /** @return BelongsTo<Topic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    protected function casts(): array
    {
        return ['reason_params' => 'array', 'stage' => 'integer', 'position' => 'integer'];
    }
}
