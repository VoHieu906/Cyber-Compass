<?php

namespace App\Modules\Content\Http\Resources;

use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A course's structure for the admin: chapters in order, each with its lessons in order
 * (every status). Used by the course page and the chapter pickers.
 *
 * @mixin Course
 */
class AdminCourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'is_published' => (bool) $this->is_published,
            'chapters' => $this->chapters->map(fn (CourseChapter $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'position' => $c->position,
                'lessons' => $c->lessons->map(fn (Lesson $l) => [
                    'id' => $l->id,
                    'slug' => $l->slug,
                    'title' => $l->title,
                    'status' => $l->status->value,
                    'has_unpublished_changes' => (bool) $l->draft_exists,
                    'position' => $l->position,
                ])->values(),
            ])->values(),
        ];
    }
}
