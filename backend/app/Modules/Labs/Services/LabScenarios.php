<?php

namespace App\Modules\Labs\Services;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/**
 * The JSON Schema of each engine's scenario (D76), in app/Modules/Labs/schemas/<engine>.json.
 * Lives here, not under Engines/, because the engine safety scan forbids file access there.
 * What a schema cannot say is checked by the engine's validateScenario().
 */
final class LabScenarios
{
    /** @var array<string, object> */
    private array $schemas = [];

    public static function directory(): string
    {
        return dirname(__DIR__).'/schemas';
    }

    public function schema(string $engine): object
    {
        return $this->schemas[$engine] ??= JsonSchemaBlock::loadSchema(self::directory()."/{$engine}.json");
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, list<string>> by path inside the scenario ('' for the scenario itself)
     */
    public function validate(string $engine, array $scenario): array
    {
        return JsonSchemaBlock::validateAgainst($this->schema($engine), $scenario);
    }

    /**
     * A small valid definition to start a new lab from, with its reference solutions.
     *
     * @return array{scenario: array<string, mixed>, solutions: array<string, mixed>}
     */
    public function starter(string $engine): array
    {
        return json_decode((string) file_get_contents(self::directory()."/fixtures/{$engine}/starter.json"), true);
    }
}
