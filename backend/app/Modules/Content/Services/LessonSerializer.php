<?php

namespace App\Modules\Content\Services;

use App\Models\LessonSection;
use Illuminate\Support\Collection;

/**
 * Turns lesson sections into their API shape. Shared by the learner reader
 * (Learning\LessonResource) and the admin editor preview, so both render exactly
 * the same thing. `id` is the section's stable UUID (ARCHITECTURE D20).
 */
final class LessonSerializer
{
    /**
     * @param  iterable<LessonSection|array{uuid: string, type: string, heading: ?string, payload: array}>  $sections
     * @return list<array{id: string, type: string, heading: ?string, payload: array}>
     */
    public function sections(iterable $sections): array
    {
        return Collection::make($sections)
            ->map(fn ($s) => $s instanceof LessonSection
                ? ['id' => $s->uuid, 'type' => $s->type, 'heading' => $s->heading, 'payload' => $s->payload]
                : ['id' => $s['uuid'], 'type' => $s['type'], 'heading' => $s['heading'] ?? null, 'payload' => $s['payload']])
            ->values()
            ->all();
    }
}
