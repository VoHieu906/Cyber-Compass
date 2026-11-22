<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A snapshot of a post as published. Written on every publish; never edited. */
#[Fillable(['post_id', 'version', 'snapshot', 'note', 'created_by'])]
class PostRevision extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer'];
    }
}
