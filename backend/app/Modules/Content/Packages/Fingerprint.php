<?php

namespace App\Modules\Content\Packages;

/**
 * A short hash of a document's working-copy sections (D67), written into the course overview and
 * sent back in a package's `base`. Keys are sorted first, because MySQL JSON reorders them.
 */
final class Fingerprint
{
    /** @param  list<array{uuid: string, type: string, heading: ?string, payload: array, sources?: ?array}>  $sections */
    public static function of(array $sections): string
    {
        $canonical = array_map(fn (array $s) => self::sorted([
            'uuid' => $s['uuid'], 'type' => $s['type'], 'heading' => $s['heading'] ?? null,
            'payload' => $s['payload'], 'sources' => $s['sources'] ?? null,
        ]), array_values($sections));

        return substr(hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 16);
    }

    private static function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::sorted(...), $value);
    }
}
