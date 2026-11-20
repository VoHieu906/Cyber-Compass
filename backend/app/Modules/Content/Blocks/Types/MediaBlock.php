<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/**
 * An uploaded image with an optional caption. The payload holds only the media uuid:
 * the URL is resolved when the lesson is read (LessonSerializer), so content never
 * stores a URL that could point elsewhere. That the uuid exists is checked by the
 * admin save action, not here, so the registry stays free of database access.
 */
final class MediaBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return 'Image';
    }

    public function description(): string
    {
        return 'An uploaded screenshot or figure with alt text and an optional caption.';
    }

    public function normalize(array $payload): array
    {
        if (is_string($payload['caption'] ?? null)) {
            $payload['caption'] = trim($payload['caption']) === '' ? null : trim($payload['caption']);
        }
        if (is_string($payload['media'] ?? null)) {
            $payload['media'] = strtolower(trim($payload['media']));
        }

        return $payload;
    }
}
