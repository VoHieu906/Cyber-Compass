<?php

namespace App\Modules\Content\CourseMap;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/**
 * The one place course maps and lesson architectures are validated (F3, D57, D59): the JSON
 * Schema (course-graph.json, architecture.json, then shapes/<shape>.json), the version upgrade
 * (MapVersions) and the reference checks a schema cannot express. Used by the lesson snapshot
 * (LessonWorkingCopy), the course graph save, the content importer and content:check-graphs.
 * The zod mirror is frontend/src/lib/graph/shapes/schemas.ts; fixtures in fixtures/ keep them
 * in step. Errors are keyed by dot path under the given prefix, like block errors.
 *
 * Adding a shape: one entry in SHAPES, shapes/<shape>.json, <shape>.valid/invalid.json fixtures,
 * and its layout in lib/graph/shapes. Nothing else changes.
 */
final class MapSchemas
{
    /** Shapes in the order the editor offers them. `contrast` is a two-column matrix (D59). */
    public const SHAPES = ['layers', 'onion', 'stack', 'queue', 'pipeline', 'cycle', 'hub', 'tree', 'mesh', 'matrix'];

    public const EDGE_TYPES = ['requires', 'builds-on', 'feeds-into', 'contrasts-with', 'parallel-to'];

    /** @var array<string, object> */
    private static array $schemas = [];

    /**
     * Validates a lesson architecture. `section` values must be UUIDs of this lesson's sections;
     * `lesson` links are only checked for form here (the caller decides what a link may name).
     *
     * @param  list<string>  $sectionUuids
     * @return array{0: array<string, list<string>>, 1: ?array<string, mixed>} [errors, clean]
     */
    public static function architecture(mixed $input, array $sectionUuids, string $prefix = 'architecture'): array
    {
        if ($input === null) {
            return [[], null];
        }
        if (! is_array($input)) {
            return [[$prefix => [__('Expected :type.', ['type' => __('an object')])]], null];
        }
        [$errors, $data] = MapVersions::upgrade($input, $prefix);
        if ($errors) {
            return [$errors, null];
        }

        $data['nodes'] ??= [];
        $data['edges'] ??= [];
        $data['shape'] ??= null;
        $errors = self::schemaErrors('architecture', $data, $prefix);
        if (! $errors && $data['shape'] !== null) {
            $errors = self::schemaErrors("shapes/{$data['shape']}", $data, $prefix);
        }
        if ($errors) {
            return [$errors, null];
        }

        $at = fn (string $path) => "{$prefix}.{$path}";
        if ($data['shape'] === null && $data['nodes'] !== []) {
            $errors[$at('shape')][] = __('Choose a shape for these nodes, or remove them.');
        }
        if ($data['shape'] !== 'matrix') {
            foreach (['rows', 'cols'] as $key) {
                if (array_key_exists($key, $data)) {
                    $errors[$at($key)][] = __('Only a matrix has rows and columns.');
                }
            }
        }

        $ids = [];
        $sections = array_flip(array_map('strtolower', $sectionUuids));
        foreach ($data['nodes'] as $i => &$node) {
            if (isset($ids[$node['id']])) {
                $errors[$at("nodes.{$i}.id")][] = __('Duplicate id ":id".', ['id' => $node['id']]);
            }
            $ids[$node['id']] = $i;
            if (isset($node['section'])) {
                $node['section'] = strtolower($node['section']);
                if (! isset($sections[$node['section']])) {
                    $errors[$at("nodes.{$i}.section")][] = __('This section is not in the lesson. Pick one of its blocks, or clear the link.');
                }
            }
        }
        unset($node);

        $seen = [];
        foreach ($data['edges'] as $i => $edge) {
            foreach (['from', 'to'] as $end) {
                if (! isset($ids[$edge[$end]])) {
                    $errors[$at("edges.{$i}.{$end}")][] = __('Unknown id ":id".', ['id' => $edge[$end]]);
                }
            }
            if ($edge['from'] === $edge['to']) {
                $errors[$at("edges.{$i}.to")][] = __('An edge needs two different nodes.');
            } elseif (isset($seen["{$edge['from']}>{$edge['to']}"])) {
                $errors[$at("edges.{$i}")][] = __('This edge is listed twice.');
            }
            $seen["{$edge['from']}>{$edge['to']}"] = true;
        }

        $errors += match ($data['shape']) {
            'tree' => self::checkTree($data['nodes'], $ids, $at),
            'matrix' => self::checkMatrix($data, $at),
            default => [],
        };

        return $errors ? [$errors, null] : [[], $data];
    }

    /**
     * Validates a course map. With $lessonSlugs (the import), every lesson reference must be one
     * of them; without (an admin save), references are left to CourseMap::issues(), because a
     * lesson that disappears later must never block saving (F3 integrity rules).
     *
     * @param  list<string>|null  $lessonSlugs
     * @return array{0: array<string, list<string>>, 1: ?array<string, mixed>} [errors, clean]
     */
    public static function courseGraph(mixed $input, ?array $lessonSlugs = null, string $prefix = 'graph'): array
    {
        if (! is_array($input)) {
            return [[$prefix => [__('Expected :type.', ['type' => __('an object')])]], null];
        }
        [$errors, $data] = MapVersions::upgrade($input, $prefix);
        if ($errors) {
            return [$errors, null];
        }
        $data['edges'] ??= [];
        $errors = self::schemaErrors('course-graph', $data, $prefix);
        if ($errors) {
            return [$errors, null];
        }

        $at = fn (string $path) => "{$prefix}.{$path}";
        $lanes = [];
        foreach ($data['lanes'] ?? [] as $i => $lane) {
            if (isset($lanes[$lane['id']])) {
                $errors[$at("lanes.{$i}.id")][] = __('Duplicate id ":id".', ['id' => $lane['id']]);
            }
            $lanes[$lane['id']] = true;
        }
        $known = $lessonSlugs === null ? null : array_flip($lessonSlugs);
        foreach ($data['placement'] ?? [] as $slug => $place) {
            if (isset($place['lane']) && ! isset($lanes[$place['lane']])) {
                $errors[$at("placement.{$slug}.lane")][] = __('Unknown id ":id".', ['id' => $place['lane']]);
            }
            if ($known !== null && ! isset($known[$slug])) {
                $errors[$at("placement.{$slug}")][] = __('No lesson ":slug" in this course.', ['slug' => $slug]);
            }
        }

        $seen = [];
        foreach ($data['edges'] as $i => $edge) {
            if ($edge['from'] === $edge['to']) {
                $errors[$at("edges.{$i}.to")][] = __('An edge needs two different lessons.');
            }
            $key = "{$edge['from']}>{$edge['to']}>{$edge['type']}";
            if (isset($seen[$key])) {
                $errors[$at("edges.{$i}")][] = __('This edge is listed twice.');
            }
            $seen[$key] = true;
            foreach (['from', 'to'] as $end) {
                if ($known !== null && ! isset($known[$edge[$end]])) {
                    $errors[$at("edges.{$i}.{$end}")][] = __('No lesson ":slug" in this course.', ['slug' => $edge[$end]]);
                }
            }
        }

        return $errors ? [$errors, null] : [[], $data];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, int>  $ids
     * @return array<string, list<string>>
     */
    private static function checkTree(array $nodes, array $ids, \Closure $at): array
    {
        $errors = [];
        $roots = 0;
        foreach ($nodes as $i => $node) {
            if (! isset($node['parent'])) {
                $roots++;

                continue;
            }
            if (! isset($ids[$node['parent']])) {
                $errors[$at("nodes.{$i}.parent")][] = __('Unknown id ":id".', ['id' => $node['parent']]);

                continue;
            }
            // Walk up: a chain longer than the node count is a loop.
            $steps = 0;
            for ($p = $node['parent']; $p !== null && $steps <= count($nodes); $steps++) {
                $p = isset($ids[$p]) ? ($nodes[$ids[$p]]['parent'] ?? null) : null;
            }
            if ($steps > count($nodes)) {
                $errors[$at("nodes.{$i}.parent")][] = __('These parents form a loop.');
            }
        }
        if ($roots !== 1) {
            $errors[$at('nodes')][] = __('A tree needs exactly one node without a parent.');
        }

        return $errors;
    }

    /** @return array<string, list<string>> */
    private static function checkMatrix(array $data, \Closure $at): array
    {
        $errors = [];
        $taken = [];
        foreach ($data['nodes'] as $i => $node) {
            [$row, $col] = $node['cell'];
            if ($row >= count($data['rows']) || $col >= count($data['cols'])) {
                $errors[$at("nodes.{$i}.cell")][] = __('This cell is outside the grid.');
            } elseif (isset($taken["{$row}:{$col}"])) {
                $errors[$at("nodes.{$i}.cell")][] = __('Another node already sits in this cell.');
            }
            $taken["{$row}:{$col}"] = true;
        }

        return $errors;
    }

    /** @return array<string, list<string>> */
    private static function schemaErrors(string $name, array $data, string $prefix): array
    {
        $schema = self::$schemas[$name] ??= JsonSchemaBlock::loadSchema(__DIR__."/schemas/{$name}.json");
        $errors = [];
        foreach (JsonSchemaBlock::validateAgainst($schema, $data) as $path => $messages) {
            $errors[rtrim("{$prefix}.{$path}", '.')] = $messages;
        }

        return $errors;
    }
}
