<?php

namespace App\Modules\Content\Http\Resources;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lesson row in the admin content list. Expects chapter.course, draft, editor and the
 * sections_count / visual_count counts to be loaded.
 *
 * @mixin Lesson
 */
class AdminLessonListResource extends JsonResource
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
            'course' => ['id' => $this->chapter->course->id, 'title' => $this->chapter->course->title],
            'chapter' => ['id' => $this->chapter->id, 'title' => $this->chapter->title, 'position' => $this->chapter->position],
            'position' => $this->position,
            'section_count' => (int) $this->sections_count,
            'visual_count' => (int) $this->visual_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'last_edited_at' => ($draft?->updated_at ?? $this->updated_at)?->toIso8601String(),
            'last_edited_by' => $draft ? $draft->editor?->name : $this->editor?->name,
        ];
    }
}
