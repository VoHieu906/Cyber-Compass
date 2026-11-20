<?php

namespace App\Modules\Content\Blocks;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

/**
 * A block type whose payload contract is a JSON Schema file in Blocks/schemas/<key>.json.
 * Subclasses usually only declare their key, label and description.
 */
abstract class JsonSchemaBlock implements BlockType
{
    private ?object $schema = null;

    public function editor(): string
    {
        return 'form';
    }

    public function schema(): object
    {
        return $this->schema ??= self::loadSchema(__DIR__."/schemas/{$this->key()}.json");
    }

    public function example(): array
    {
        return (array) json_decode(json_encode($this->schema()->examples[0] ?? new \stdClass), true);
    }

    public function normalize(array $payload): array
    {
        return $payload;
    }

    public function validate(array $payload): array
    {
        return self::validateAgainst($this->schema(), $payload);
    }

    /**
     * @return array<string, list<string>>
     */
    protected static function validateAgainst(object $schema, mixed $payload): array
    {
        $validator = new Validator;
        $validator->setMaxErrors(20);

        // JSON Schema distinguishes objects from arrays; PHP arrays from JSON are decoded both ways,
        // so round-trip through JSON to get the object form opis expects.
        $data = json_decode(json_encode($payload === [] ? new \stdClass : $payload));
        $result = $validator->validate($data, $schema);

        if ($result->isValid()) {
            return [];
        }

        $errors = [];
        foreach ((new ErrorFormatter)->format($result->error(), true) as $pointer => $messages) {
            $path = trim(str_replace('/', '.', (string) $pointer), '.');
            $errors[$path] = array_values(array_unique((array) $messages));
        }

        return $errors;
    }

    protected static function loadSchema(string $file): object
    {
        $schema = json_decode((string) @file_get_contents($file));
        if (! $schema instanceof \stdClass) {
            throw new RuntimeException("Block schema missing or invalid: {$file}");
        }

        return $schema;
    }
}
