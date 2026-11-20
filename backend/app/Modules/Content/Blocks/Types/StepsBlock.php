<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

final class StepsBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'steps';
    }

    public function label(): string
    {
        return 'Steps';
    }

    public function description(): string
    {
        return 'A numbered procedure or worked example.';
    }

    public function normalize(array $payload): array
    {
        if (is_array($payload['items'] ?? null)) {
            $payload['items'] = array_values(array_map(fn ($i) => is_string($i) ? trim($i) : $i, $payload['items']));
        }

        return $payload;
    }
}
