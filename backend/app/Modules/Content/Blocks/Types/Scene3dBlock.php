<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/**
 * A declarative interactive 3D scene (docs/SANDBOXED_3D.md, ARCHITECTURE D22). Pure data,
 * drawn by our renderer inside the sandbox; nothing in the payload executes. The JSON Schema
 * covers shapes and budgets; the cross-references are checked here (the zod mirror does the same).
 */
final class Scene3dBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'scene-3d';
    }

    public function label(): string
    {
        return '3D scene';
    }

    public function description(): string
    {
        return 'An interactive 3D scene built from shapes, labels, links with moving packets, animations and guided steps.';
    }

    public function editor(): string
    {
        return 'scene';
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
        $payload['aspect'] ??= '16:9';

        return $payload;
    }

    public function validate(array $payload): array
    {
        $errors = parent::validate($payload);
        if ($errors !== []) {
            return $errors; // cross-references only make sense on a well-formed scene
        }

        $ids = [];
        foreach ($payload['objects'] as $i => $object) {
            if (array_key_exists($object['id'], $ids)) {
                $errors["objects.{$i}.id"][] = __('Duplicate id ":id".', ['id' => $object['id']]);
            }
            $ids[$object['id']] = $object['parent'] ?? null;
        }

        foreach ($payload['objects'] as $i => $object) {
            if (! isset($object['parent'])) {
                continue;
            }
            if (! array_key_exists($object['parent'], $ids)) {
                $errors["objects.{$i}.parent"][] = __('Unknown object ":id".', ['id' => $object['parent']]);

                continue;
            }
            // Walk up the chain: a loop would never finish when the scene is built.
            $seen = [$object['id'] => true];
            for ($p = $object['parent']; $p !== null; $p = $ids[$p] ?? null) {
                if (isset($seen[$p])) {
                    $errors["objects.{$i}.parent"][] = __('Parents form a loop.');
                    break;
                }
                $seen[$p] = true;
            }
        }

        foreach ($payload['links'] ?? [] as $i => $link) {
            foreach (['from', 'to'] as $end) {
                if (! array_key_exists($link[$end], $ids)) {
                    $errors["links.{$i}.{$end}"][] = __('Unknown object ":id".', ['id' => $link[$end]]);
                }
            }
        }

        foreach ($payload['steps'] ?? [] as $i => $step) {
            foreach (['show', 'hide', 'highlight'] as $list) {
                foreach ($step[$list] ?? [] as $j => $ref) {
                    if (! array_key_exists($ref, $ids)) {
                        $errors["steps.{$i}.{$list}.{$j}"][] = __('Unknown object ":id".', ['id' => $ref]);
                    }
                }
            }
        }

        return $errors;
    }
}
