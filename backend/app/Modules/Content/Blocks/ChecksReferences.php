<?php

namespace App\Modules\Content\Blocks;

/**
 * Cross-reference checks that JSON Schema cannot express (unique ids, references to them,
 * parent chains). Messages are keyed by dot path like the schema errors. The zod mirrors
 * in frontend/src/features/learning/blocks/schemas.ts apply the same rules.
 */
trait ChecksReferences
{
    /**
     * Records each item's id and reports duplicates.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, list<string>>  $errors
     * @return array<string, int> id => index
     */
    protected static function collectIds(array $items, string $path, array &$errors): array
    {
        $ids = [];
        foreach ($items as $i => $item) {
            $id = $item['id'];
            if (array_key_exists($id, $ids)) {
                $errors["{$path}.{$i}.id"][] = __('Duplicate id ":id".', ['id' => $id]);
            }
            $ids[$id] ??= $i;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $ids
     * @param  array<string, list<string>>  $errors
     */
    protected static function mustExist(mixed $ref, array $ids, string $path, array &$errors): bool
    {
        if (is_string($ref) && array_key_exists($ref, $ids)) {
            return true;
        }
        $errors[$path][] = __('Unknown id ":id".', ['id' => (string) $ref]);

        return false;
    }
}
