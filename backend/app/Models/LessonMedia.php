<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** An uploaded lesson image (always WebP). Referenced from `media` blocks by its uuid. */
#[Fillable(['uuid', 'path', 'source', 'alt', 'width', 'height', 'size_bytes', 'uploaded_by'])]
class LessonMedia extends Model
{
    protected $table = 'lesson_media';

    public function url(): string
    {
        return Storage::disk(config('cybercompass.uploads_disk'))->url($this->path);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'size_bytes' => 'integer'];
    }
}
