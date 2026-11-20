<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\ChecksReferences;
use App\Modules\Content\Blocks\JsonSchemaBlock;

/** A process or decision flow; the frontend lays it out automatically (lib/graph). */
final class FlowBlock extends JsonSchemaBlock
{
    use ChecksReferences;

    public function key(): string
    {
        return 'flow';
    }

    public function label(): string
    {
        return 'Flow';
    }

    public function description(): string
    {
        return 'A process or decision flow of steps and choices, laid out automatically.';
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

        $nodes = self::collectIds($payload['nodes'], 'nodes', $errors);
        $seen = [];
        foreach ($payload['edges'] as $i => $edge) {
            $ok = self::mustExist($edge['from'], $nodes, "edges.{$i}.from", $errors)
                & self::mustExist($edge['to'], $nodes, "edges.{$i}.to", $errors);
            if (! $ok) {
                continue;
            }
            if ($edge['from'] === $edge['to']) {
                $errors["edges.{$i}.to"][] = __('An edge needs two different nodes.');
            } elseif (isset($seen["{$edge['from']}>{$edge['to']}"])) {
                $errors["edges.{$i}"][] = __('This edge is listed twice.');
            }
            $seen["{$edge['from']}>{$edge['to']}"] = true;
        }

        return $errors;
    }
}
