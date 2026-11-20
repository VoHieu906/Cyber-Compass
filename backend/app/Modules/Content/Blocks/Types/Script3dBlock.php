<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/**
 * Three.js code written by an admin (docs/SANDBOXED_3D.md, ARCHITECTURE D22). The server never
 * runs or inspects it; it is only ever executed in the sandboxed worker on the client, which has
 * no origin, storage or network. Operators can switch the type off (CONTENT_SCRIPTED_3D=false):
 * saves are then refused and learners get the text description instead (LessonSerializer).
 */
final class Script3dBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'script-3d';
    }

    public function label(): string
    {
        return '3D script';
    }

    public function description(): string
    {
        return 'Three.js code for 3D content a scene cannot express, run in an isolated sandbox.';
    }

    public function editor(): string
    {
        return 'script';
    }

    public static function enabled(): bool
    {
        return (bool) config('cybercompass.content.scripted_3d', true);
    }

    public function normalize(array $payload): array
    {
        foreach (['title', 'description'] as $field) {
            if (is_string($payload[$field] ?? null)) {
                $payload[$field] = trim($payload[$field]);
            }
        }
        if (is_string($payload['caption'] ?? null) && trim($payload['caption']) === '') {
            $payload['caption'] = null;
        }
        if (is_string($payload['code'] ?? null)) {
            $code = rtrim(str_replace("\r\n", "\n", $payload['code']));
            // Whitespace-only code stays empty, so the schema reports it as missing.
            $payload['code'] = $code === '' ? '' : $code."\n";
        }
        $payload['aspect'] ??= '16:9';

        return $payload;
    }

    public function validate(array $payload): array
    {
        if (! self::enabled()) {
            return ['code' => [__('3D scripts are turned off on this server. Use a 3D scene instead.')]];
        }

        return parent::validate($payload);
    }
}
