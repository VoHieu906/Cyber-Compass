<?php

namespace App\Modules\Content\Http\Resources;

use App\Models\Lesson;
use App\Models\Topic;
use App\Modules\Content\Actions\LessonLifecycle;
use App\Modules\Content\Services\LessonSerializer;
use App\Modules\Content\Services\LessonWorkingCopy;
use App\Modules\Learning\Services\ExerciseReferences;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lesson in the editor: the working copy (draft if any, else live) to edit, the same
 * sections serialized exactly as learners get them (for the preview), and the state the
 * editor needs to save safely (lock_version, has_unpublished_changes).
 *
 * @mixin Lesson
 */
class AdminLessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $copy = app(LessonWorkingCopy::class);
        $working = $copy->current($this->resource);
        $chapter = $this->chapter;
        $draft = $this->draft;

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
            'course' => ['id' => $chapter->course->id, 'slug' => $chapter->course->slug, 'title' => $chapter->course->title, 'is_published' => (bool) $chapter->course->is_published],
            'chapter' => ['id' => $chapter->id, 'title' => $chapter->title],
            'topics' => $this->topics->map(fn (Topic $t) => ['slug' => $t->slug, 'title' => $t->title])->values(),
            'can_delete' => app(LessonLifecycle::class)->canDelete($this->resource),
            // Sections exercise questions point at (D30): removing one is refused on publish, so the editor warns.
            'exercise_references' => (object) app(ExerciseReferences::class)->forLesson($this->id),
            'working_copy' => [
                ...collect($working)->except('sections')->all(),
                'sections' => array_map(fn (array $s) => [
                    'id' => $s['uuid'], 'type' => $s['type'], 'heading' => $s['heading'], 'payload' => $s['payload'],
                ], $working['sections']),
            ],
            'preview_sections' => app(LessonSerializer::class)->sections($working['sections']),
        ];
    }
}
