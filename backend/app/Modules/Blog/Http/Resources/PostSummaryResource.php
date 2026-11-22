<?php

namespace App\Modules\Blog\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post card in the public list. Never carries sections, so long posts stay out of list
 * responses. Expects coverMedia and author loaded.
 *
 * @mixin Post
 */
class PostSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'tags' => $this->tags ?? [],
            'estimated_minutes' => $this->estimated_minutes,
            'published_at' => $this->published_at?->toIso8601String(),
            'author' => $this->author?->name,
            'cover' => $this->coverMedia ? new CoverResource($this->coverMedia) : null,
        ];
    }
}
