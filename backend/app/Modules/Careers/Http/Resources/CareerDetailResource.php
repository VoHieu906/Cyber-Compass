<?php

namespace App\Modules\Careers\Http\Resources;

use App\Models\Career;
use App\Models\Skill;
use App\Models\Topic;
use App\Modules\Catalog\Http\Resources\CourseSummaryResource;
use Illuminate\Http\Request;

/**
 * A career page: what the role does, what it needs, and the roadmap template
 * (topics in stages with their prerequisites). The roadmap is data, not layout:
 * the client decides how to draw it.
 *
 * @mixin Career
 */
class CareerDetailResource extends CareerSummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'responsibilities' => $this->responsibilities,
            'skills' => $this->skills->map(fn (Skill $s) => ['slug' => $s->slug, 'name' => $s->name])->values(),
            'suggested_courses' => CourseSummaryResource::collection($this->whenLoaded('suggestedCourses')),
            'roadmap' => [
                'stages' => $this->topics
                    ->groupBy('pivot.stage')
                    ->map(fn ($topics, $stage) => [
                        'stage' => (int) $stage,
                        'topics' => $topics->map(fn (Topic $t) => [
                            'slug' => $t->slug,
                            'title' => $t->title,
                            'summary' => $t->summary,
                            'domain' => ['slug' => $t->domain->slug, 'name' => $t->domain->name],
                            'lesson_count' => (int) $t->lessons_count,
                            'requires' => $t->prerequisites->pluck('slug')->values(),
                        ])->values(),
                    ])
                    ->values(),
            ],
        ];
    }
}
