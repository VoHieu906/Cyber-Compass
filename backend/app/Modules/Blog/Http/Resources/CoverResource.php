<?php

namespace App\Modules\Blog\Http\Resources;

use App\Models\LessonMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post cover image: an uploaded lesson_media row.
 *
 * @mixin LessonMedia
 */
class CoverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'url' => $this->url(),
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
