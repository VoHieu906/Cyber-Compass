<?php

namespace App\Modules\Blog\Http\Resources;

use App\Models\Post;
use App\Modules\Content\Services\BlockSerializer;
use Illuminate\Http\Request;

/**
 * A post for the reader: the card fields plus the sections exactly as the lesson reader
 * gets them (BlockSerializer). Expects sections, coverMedia and author loaded.
 *
 * @mixin Post
 */
class PostResource extends PostSummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'sections' => app(BlockSerializer::class)->sections($this->sections),
        ];
    }
}
