<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Lesson;
use App\Models\Skill;
use App\Modules\Content\CourseMap\CourseMap;
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
            // Chapters with no visible lessons (all drafts or archived) are not shown to learners.
            'chapters' => $this->chapters->filter(fn (CourseChapter $chapter) => $chapter->lessons->isNotEmpty())->map(fn (CourseChapter $chapter) => [
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
            // Built from the lessons above, so it only ever names lessons learners may see (F3).
            'graph' => app(CourseMap::class)->forLearners($this->resource),
        ];
    }
}
