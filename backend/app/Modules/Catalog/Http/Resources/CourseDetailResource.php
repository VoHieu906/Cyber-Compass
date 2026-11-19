<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Lesson;
use App\Models\Skill;
use Illuminate\Http\Request;

/**
 * The public course page. Learner-specific state comes from /courses/{slug}/progress,
 * so this response is the same for everyone and can be cached.
 *
 * @mixin Course
 */
class CourseDetailResource extends CourseSummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'objectives' => $this->objectives,
            'skills' => $this->skills->map(fn (Skill $s) => ['slug' => $s->slug, 'name' => $s->name])->values(),
            'prerequisites' => $this->prerequisites->map(fn (Course $c) => ['slug' => $c->slug, 'title' => $c->title])->values(),
            'chapters' => $this->chapters->map(fn (CourseChapter $chapter) => [
                'title' => $chapter->title,
                'position' => $chapter->position,
                'lessons' => $chapter->lessons->map(fn (Lesson $lesson) => [
                    'slug' => $lesson->slug,
                    'title' => $lesson->title,
                    'summary' => $lesson->summary,
                    'estimated_minutes' => $lesson->estimated_minutes,
                ])->values(),
            ])->values(),
            'related' => CourseSummaryResource::collection($this->whenLoaded('related')),
        ];
    }
}
