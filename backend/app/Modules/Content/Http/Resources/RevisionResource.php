<?php

namespace App\Modules\Content\Http\Resources;

use App\Models\LessonRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One published version. Expects `author` loaded and a `summary` attribute set by the
 * controller (RevisionSummary).
 *
 * @mixin LessonRevision
 */
class RevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version,
            'title' => $this->snapshot['title'] ?? null,
            'note' => $this->note,
            'author' => $this->author?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'section_count' => count($this->snapshot['sections'] ?? []),
            'changes' => $this->resource->getAttributes()['summary'] ?? null,
        ];
    }
}
