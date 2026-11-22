<?php

namespace App\Modules\Blog\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post row in the admin list. Expects draft.editor, editor and sections_count loaded.
 *
 * @mixin Post
 */
class AdminPostListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $draft = $this->draft;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $draft?->snapshot['title'] ?? $this->title,
            'status' => $this->status->value,
            'has_unpublished_changes' => $draft !== null,
            'tags' => $draft?->snapshot['tags'] ?? $this->tags ?? [],
            'section_count' => (int) $this->sections_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'last_edited_at' => ($draft?->updated_at ?? $this->updated_at)?->toIso8601String(),
            'last_edited_by' => $draft ? $draft->editor?->name : $this->editor?->name,
        ];
    }
}
