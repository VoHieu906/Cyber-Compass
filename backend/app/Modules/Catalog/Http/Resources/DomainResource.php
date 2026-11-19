<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Domain */
class DomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'course_count' => $this->whenCounted('courses'),
        ];
    }
}
