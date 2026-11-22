<?php

namespace App\Modules\Blog\Http\Resources;

use App\Models\LessonMedia;
use App\Models\Post;
use App\Modules\Blog\Actions\PostLifecycle;
use App\Modules\Blog\Services\PostWorkingCopy;
use App\Modules\Content\Services\BlockSerializer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post in the editor: the working copy (draft if any, else live), the preview sections exactly
 * as readers get them, and the state the editor needs to save safely. The shared fields have
 * the same shape as AdminLessonResource, so one editor serves both (D55).
 *
 * @mixin Post
 */
class AdminPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $copy = app(PostWorkingCopy::class);
        $working = $copy->current($this->resource);
        $draft = $this->draft;
        $cover = $working['cover'] ? LessonMedia::query()->where('uuid', $working['cover'])->first() : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'slug_editable' => $this->published_at === null,
            'has_unpublished_changes' => $draft !== null,
            'lock_version' => $copy->lockVersion($this->resource),
            'draft' => $draft ? [
                'updated_at' => $draft->updated_at?->toIso8601String(),
                'updated_by' => $draft->editor?->name,
            ] : null,
            'can_delete' => app(PostLifecycle::class)->canDelete($this->resource),
            'cover' => $cover ? new CoverResource($cover) : null,
            'working_copy' => [
                ...collect($working)->except('sections')->all(),
                'sections' => array_map(fn (array $s) => [
                    'id' => $s['uuid'], 'type' => $s['type'], 'heading' => $s['heading'], 'payload' => $s['payload'],
                ], $working['sections']),
            ],
            'preview_sections' => app(BlockSerializer::class)->sections($working['sections']),
        ];
    }
}
