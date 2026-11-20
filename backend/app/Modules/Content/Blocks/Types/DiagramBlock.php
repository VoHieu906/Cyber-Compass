<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/**
 * A bespoke diagram: a component on the frontend (features/learning/blocks) drawing data
 * from the lesson (D14). Each diagram key has its own data schema in schemas/diagrams/<key>.json;
 * the set of files there is the set of allowed keys.
 */
final class DiagramBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'diagram';
    }

    public function label(): string
    {
        return 'Bespoke diagram';
    }

    public function description(): string
    {
        return 'A purpose-built interactive diagram, chosen by key, drawing data from the lesson.';
    }

    public function editor(): string
    {
        return 'json';
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_map(fn ($f) => basename($f, '.json'), glob(__DIR__.'/../schemas/diagrams/*.json') ?: []);
    }

    public function validate(array $payload): array
    {
        $errors = parent::validate($payload);
        $key = $payload['key'] ?? null;

        if (! is_string($key) || ! in_array($key, self::keys(), true)) {
            return $errors + ['key' => ['Unknown diagram. Allowed: '.implode(', ', self::keys())]];
        }

        $dataErrors = self::validateAgainst(self::loadSchema(__DIR__."/../schemas/diagrams/{$key}.json"), $payload['data'] ?? []);
        foreach ($dataErrors as $path => $messages) {
            $errors[trim("data.{$path}", '.')] = $messages;
        }

        return $errors;
    }
}
