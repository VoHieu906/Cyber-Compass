<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

final class CalloutBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'callout';
    }

    public function label(): string
    {
        return 'Callout';
    }

    public function description(): string
    {
        return 'A short note set apart from the text: info, tip, warning, a recap that says the block before it another way, or a fun fact.';
    }

    public function normalize(array $payload): array
    {
        $payload['tone'] ??= 'info';
        if (is_string($payload['text'] ?? null)) {
            $payload['text'] = trim($payload['text']);
        }

        return $payload;
    }
}
