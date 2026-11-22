<?php

namespace App\Modules\Content\Documents;

/**
 * Estimated reading time of a block document, computed from its content (posts, D55).
 * Every word in headings and payload strings counts at 200 words a minute; an interactive
 * block adds half a minute for working with it. Identifiers (ids, keys, colours, URLs) are skipped.
 */
final class ReadingTime
{
    public const WORDS_PER_MINUTE = 200;

    private const INTERACTIVE = ['scene-3d', 'script-3d', 'step-through', 'calculator', 'knowledge-check', 'bitfield'];

    /** Payload keys that hold identifiers or code, not reading text. */
    private const SKIP_KEYS = ['id', 'key', 'from', 'to', 'parent', 'tone', 'language', 'color', 'media', 'url', 'code', 'kind', 'shape', 'icon', 'target'];

    /** @param  list<array{type: string, heading: ?string, payload: array}>  $sections */
    public static function minutes(array $sections): int
    {
        $words = 0;
        $interactive = 0;
        foreach ($sections as $section) {
            $words += self::words($section['heading'] ?? '');
            $words += self::countIn($section['payload'] ?? []);
            if ($section['type'] === 'code') {
                // Code is read more slowly than prose: one "word" per line.
                $words += substr_count((string) ($section['payload']['code'] ?? ''), "\n") + 1;
            }
            if (in_array($section['type'], self::INTERACTIVE, true)) {
                $interactive++;
            }
        }

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE + $interactive / 2));
    }

    private static function countIn(mixed $value, ?string $key = null): int
    {
        if (is_string($value)) {
            return in_array($key, self::SKIP_KEYS, true) ? 0 : self::words($value);
        }
        if (! is_array($value)) {
            return 0;
        }
        $n = 0;
        foreach ($value as $k => $v) {
            $n += self::countIn($v, is_string($k) ? $k : $key);
        }

        return $n;
    }

    private static function words(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $text);
    }
}
