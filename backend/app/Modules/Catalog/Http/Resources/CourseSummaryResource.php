<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A course as shown on a catalog card.
 *
 * @mixin Course
 */
class CourseSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'domain' => ['slug' => $this->domain->slug, 'name' => $this->domain->name],
            'difficulty' => $this->difficulty->slug(),
            'lesson_count' => (int) $this->lessons_count,
            'estimated_minutes' => (int) $this->estimated_minutes,
            'thumbnail_url' => $this->thumbnail_path,
            'published_at' => $this->published_at?->toIso8601String(),
            // Present only for a signed-in viewer.
            'is_favorited' => $this->when(isset($this->is_favorited), fn () => (bool) $this->is_favorited),
        ];
    }
}
