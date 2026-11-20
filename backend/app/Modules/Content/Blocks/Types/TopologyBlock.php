<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\ChecksReferences;
use App\Modules\Content\Blocks\JsonSchemaBlock;

/** Devices, links and zones at positions the author chooses (percent of the canvas). */
final class TopologyBlock extends JsonSchemaBlock
{
    use ChecksReferences;

    public function key(): string
    {
        return 'topology';
    }

    public function label(): string
    {
        return 'Network topology';
    }

    public function description(): string
    {
        return 'Devices and links placed on a canvas, with optional zones such as a DMZ.';
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

        $zones = self::collectIds($payload['zones'] ?? [], 'zones', $errors);
        $devices = self::collectIds($payload['devices'], 'devices', $errors);
        foreach (array_intersect_key($zones, $devices) as $id => $i) {
            $errors["zones.{$i}.id"][] = __('Duplicate id ":id".', ['id' => $id]);
        }
        foreach ($payload['zones'] ?? [] as $i => $zone) {
            if ($zone['x'] + $zone['w'] > 100 || $zone['y'] + $zone['h'] > 100) {
                $errors["zones.{$i}"][] = __('The zone goes past the edge of the canvas.');
            }
        }
        foreach ($payload['links'] as $i => $link) {
            $ok = self::mustExist($link['from'], $devices, "links.{$i}.from", $errors)
                & self::mustExist($link['to'], $devices, "links.{$i}.to", $errors);
            if ($ok && $link['from'] === $link['to']) {
                $errors["links.{$i}.to"][] = __('A link needs two different devices.');
            }
        }

        return $errors;
    }
}
