<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

final class CodeBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'code';
    }

    public function label(): string
    {
        return 'Code';
    }

    public function description(): string
    {
        return 'A command, script, log or config file, shown verbatim with a copy button.';
    }

    public function normalize(array $payload): array
    {
        $payload['language'] ??= 'text';
        if (is_string($payload['code'] ?? null)) {
            $payload['code'] = rtrim($payload['code']);
        }

        return $payload;
    }
}
