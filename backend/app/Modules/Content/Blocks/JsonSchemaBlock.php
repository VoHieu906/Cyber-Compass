<?php

namespace App\Modules\Content\Blocks;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
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

        $formatter = new ErrorFormatter;
        $errors = [];
        $messages = $formatter->format(
            $result->error(),
            true,
            fn (ValidationError $e) => self::message($e, $formatter),
            // A missing property is reported at the property itself, so the editor can show it on that field.
            fn (ValidationError $e) => $formatter->formatErrorKey($e)
                .($e->keyword() === 'required' ? '/'.((array) $e->args()['missing'])[0] : ''),
        );
        foreach ($messages as $pointer => $list) {
            $path = trim(str_replace('/', '.', (string) $pointer), '.');
            $errors[$path] = array_values(array_unique((array) $list));
        }

        return $errors;
    }

    /**
     * Authors read these in the editor, so they name the problem in plain words (and are
     * translatable) instead of the schema library's wording.
     */
    private static function message(ValidationError $e, ErrorFormatter $formatter): string
    {
        $args = $e->args();

        return match ($e->keyword()) {
            'required' => __('This field is required.'),
            'type' => __('Expected :type.', ['type' => self::typeName($args['expected'] ?? null)]),
            'minLength' => ($args['min'] ?? 0) <= 1
                ? __('This can not be empty.')
                : __('Use at least :min characters.', ['min' => $args['min']]),
            'maxLength' => __('Use at most :max characters.', ['max' => $args['max'] ?? '']),
            'minItems' => __('Add at least :min items.', ['min' => $args['min'] ?? '']),
            'maxItems' => __('Use at most :max items.', ['max' => $args['max'] ?? '']),
            'enum' => __('Choose one of: :values.', ['values' => implode(', ', (array) ($e->schema()->info()->data()->enum ?? []))]),
            'pattern' => __('This is not in the expected format.'),
            'additionalProperties' => __('Unknown field: :fields.', ['fields' => implode(', ', (array) ($args['properties'] ?? []))]),
            'minimum' => __('Use :min or more.', ['min' => $args['min'] ?? '']),
            'maximum' => __('Use :max or less.', ['max' => $args['max'] ?? '']),
            default => $formatter->formatErrorMessage($e),
        };
    }

    private static function typeName(mixed $expected): string
    {
        $names = ['string' => __('text'), 'array' => __('a list'), 'object' => __('an object'), 'integer' => __('a whole number'), 'number' => __('a number'), 'boolean' => __('true or false'), 'null' => __('nothing')];

        return implode(__(' or '), array_map(fn ($t) => $names[$t] ?? $t, (array) $expected));
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
