<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\ChecksReferences;
use App\Modules\Content\Blocks\JsonSchemaBlock;

/** A hierarchy with exactly one root; each node names its parent. */
final class TreeBlock extends JsonSchemaBlock
{
    use ChecksReferences;

    public function key(): string
    {
        return 'tree';
    }

    public function label(): string
    {
        return 'Tree';
    }

    public function description(): string
    {
        return 'A hierarchy with one root, such as the DNS hierarchy or a folder structure.';
    }

    public function editor(): string
    {
        return 'json';
    }

    public function validate(array $payload): array
    {
        $errors = parent::validate($payload);
        if ($errors !== []) {
            return $errors;
        }

        $ids = self::collectIds($payload['nodes'], 'nodes', $errors);
        $parents = [];
        $roots = [];
        foreach ($payload['nodes'] as $i => $node) {
            $parents[$node['id']] = $node['parent'] ?? null;
            if (! isset($node['parent'])) {
                $roots[] = $i;
            }
        }
        if (count($roots) !== 1) {
            $errors['nodes'][] = __('A tree needs exactly one node without a parent (it has :count).', ['count' => count($roots)]);
        }

        foreach ($payload['nodes'] as $i => $node) {
            if (! isset($node['parent']) || ! self::mustExist($node['parent'], $ids, "nodes.{$i}.parent", $errors)) {
                continue;
            }
            $seen = [$node['id'] => true];
            for ($p = $node['parent']; $p !== null; $p = $parents[$p] ?? null) {
                if (isset($seen[$p])) {
                    $errors["nodes.{$i}.parent"][] = __('Parents form a loop.');
                    break;
                }
                $seen[$p] = true;
            }
        }

        return $errors;
    }
}
