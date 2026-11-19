<?php

namespace App\Modules\Learning\Http\Resources;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lesson for reading. Expects relations loaded by LessonController:
 * sections, chapter.course.prerequisites, plus `navigation` and `viewerProgress`.
 *
 * @mixin Lesson
 */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $course = $this->chapter->course;
        /** @var LessonProgress|null $progress */
        $progress = $this->getRelation('viewerProgress');
        $navigation = $this->getRelation('navigation');

        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'estimated_minutes' => $this->estimated_minutes,
            'objectives' => $this->objectives,
            'key_takeaways' => $this->key_takeaways,
            'resources' => $this->resources,
            'course' => ['slug' => $course->slug, 'title' => $course->title, 'domain' => $course->domain->name],
            'chapter' => ['title' => $this->chapter->title, 'position' => $this->chapter->position],
            'prerequisites' => $course->prerequisites->map(fn (Course $c) => ['slug' => $c->slug, 'title' => $c->title])->values(),
            'sections' => $this->sections->map(fn (LessonSection $s) => [
                'id' => "section-{$s->position}",
                'type' => $s->type->value,
                'heading' => $s->heading,
                'payload' => $s->payload,
            ])->values(),
            'navigation' => $navigation,
            'progress' => [
                'status' => $progress?->status->value ?? 'not_started',
                'completed_at' => $progress?->completed_at?->toIso8601String(),
            ],
        ];
    }
}
