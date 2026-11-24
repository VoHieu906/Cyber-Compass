<?php

namespace App\Modules\Content\Documents;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/**
 * A section's sources (D68): one rule for every block type, checked against schemas/sources.json
 * (mirrored in zod with the shared fixtures). Called by BlockRegistry::validateSections, so the
 * editor, the content files and packages all validate here. URLs are never fetched.
 */
final class SectionSources
{
    private static ?object $schema = null;

    /**
     * [errors keyed inside the list, the clean list or null when empty].
     *
     * @return array{0: array<string, list<string>>, 1: list<array<string, string>>|null}
     */
    public static function validate(mixed $sources): array
    {
        if ($sources === null || $sources === []) {
            return [[], null];
        }
        if (! is_array($sources) || ! array_is_list($sources)) {
            return [['' => [__('Expected a list.')]], null];
        }

        $clean = array_map(fn ($s) => is_array($s) ? array_map(fn ($v) => is_string($v) ? trim($v) : $v, $s) : $s, $sources);
        $errors = JsonSchemaBlock::validateAgainst(self::$schema ??= JsonSchemaBlock::loadSchema(__DIR__.'/schemas/sources.json'), $clean);

        return [$errors, $errors ? null : $clean];
    }
}
