<?php

namespace App\Modules\Content\Services;

use App\Models\LessonMedia;
use App\Models\LessonSection;
use App\Modules\Content\Blocks\Types\Script3dBlock;
use Illuminate\Support\Collection;

/**
 * Turns lesson sections into their API shape. Shared by the learner reader
 * (Learning\LessonResource) and the admin editor preview, so both render exactly
 * the same thing. `id` is the section's stable UUID (ARCHITECTURE D20).
 *
 * `media` blocks store only an image uuid; here it is resolved to {url, alt, width, height}
 * with one query for the whole lesson. A missing image yields url null, and the reader
 * shows nothing rather than a broken image.
 */
final class LessonSerializer
{
    /**
     * @param  iterable<LessonSection|array{uuid: string, type: string, heading: ?string, payload: array}>  $sections
     * @return list<array{id: string, type: string, heading: ?string, payload: array}>
     */
    public function sections(iterable $sections): array
    {
        $rows = Collection::make($sections)
            ->map(fn ($s) => $s instanceof LessonSection
                ? ['id' => $s->uuid, 'type' => $s->type, 'heading' => $s->heading, 'payload' => $s->payload]
                : ['id' => $s['uuid'], 'type' => $s['type'], 'heading' => $s['heading'] ?? null, 'payload' => $s['payload']])
            ->values();

        // With 3D scripts switched off, the code never leaves the server; the reader shows the description.
        if (! Script3dBlock::enabled()) {
            $rows = $rows->map(fn (array $row) => $row['type'] === 'script-3d'
                ? [...$row, 'payload' => [...collect($row['payload'])->except('code')->all(), 'disabled' => true]]
                : $row);
        }

        $mediaIds = $rows->where('type', 'media')->pluck('payload.media')->filter()->unique();
        if ($mediaIds->isEmpty()) {
            return $rows->all();
        }

        $media = LessonMedia::query()->whereIn('uuid', $mediaIds)->get()->keyBy('uuid');

        return $rows->map(function (array $row) use ($media) {
            if ($row['type'] !== 'media') {
                return $row;
            }
            $image = $media->get($row['payload']['media'] ?? '');
            $row['payload'] += [
                'url' => $image?->url(),
                'alt' => $image?->alt ?? '',
                'width' => $image?->width,
                'height' => $image?->height,
            ];

            return $row;
        })->all();
    }
}
