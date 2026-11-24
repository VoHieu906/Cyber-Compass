<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One block of a blog post: the twin of LessonSection (ARCHITECTURE D55). */
#[Fillable(['uuid', 'post_id', 'position', 'type', 'heading', 'payload', 'sources'])]
class PostSection extends Model
{
    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'sources' => 'array'];
    }
}
