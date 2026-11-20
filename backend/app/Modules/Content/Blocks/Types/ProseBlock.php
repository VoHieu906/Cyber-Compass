<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

final class ProseBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'prose';
    }

    public function label(): string
    {
        return 'Text';
    }

    public function description(): string
    {
        return 'Paragraphs and bullet lists with **bold** and `code`.';
    }

    public function normalize(array $payload): array
    {
        return is_string($payload['text'] ?? null) ? ['text' => trim($payload['text'])] + $payload : $payload;
    }
}
