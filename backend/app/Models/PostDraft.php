<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Unpublished edits to a post: a validated snapshot of its metadata and ordered sections (twin of LessonDraft). */
#[Fillable(['post_id', 'snapshot', 'lock_version', 'updated_by'])]
class PostDraft extends Model
{
    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'lock_version' => 'integer'];
    }
}
