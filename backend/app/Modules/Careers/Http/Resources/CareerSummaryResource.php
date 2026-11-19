<?php

namespace App\Modules\Careers\Http\Resources;

use App\Models\Career;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Career */
class CareerSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'domains' => $this->domains
                ->sortByDesc('pivot.required_level')
                ->map(fn (Domain $d) => ['slug' => $d->slug, 'name' => $d->name, 'required_level' => (int) $d->pivot->required_level])
                ->values(),
            'topic_count' => $this->whenCounted('topics'),
        ];
    }
}
