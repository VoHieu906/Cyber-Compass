<?php

namespace App\Modules\Content\Services;

/**
 * Prints nested arrays as readable PHP source in the content-file style: short arrays,
 * four-space indents, lists of scalars on one line when short, and multi-line text as
 * nowdoc blocks so prose stays readable in diffs. Output is valid PHP that evaluates to
 * exactly the input (strings are never altered).
 */
final class PhpArrayWriter
{
    private const INLINE_MAX = 100;

    public static function write(mixed $value, int $depth = 0): string
    {
        if (is_array($value)) {
            return self::array($value, $depth);
        }
        if (is_string($value)) {
            return self::string($value, $depth);
        }

        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_float($value) => self::float($value),
            default => (string) $value,
        };
    }

    /** @param  array<int|string, mixed>  $value */
    private static function array(array $value, int $depth): string
    {
        if ($value === []) {
            return '[]';
        }
        $isList = array_is_list($value);

        // Short lists of scalars, and short tuples, stay on one line.
        $inline = '['.implode(', ', array_map(
            fn ($k, $v) => ($isList ? '' : self::key($k).' => ').(is_array($v) ? self::write($v, $depth + 1) : self::write($v, $depth + 1)),
            array_keys($value),
            $value,
        )).']';
        if (! str_contains($inline, "\n") && strlen($inline) + $depth * 4 <= self::INLINE_MAX) {
            return $inline;
        }

        $pad = str_repeat('    ', $depth + 1);
        $lines = [];
        foreach ($value as $k => $v) {
            $lines[] = $pad.($isList ? '' : self::key($k).' => ').self::write($v, $depth + 1).',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth).']';
    }

    private static function key(int|string $key): string
    {
        return is_int($key) ? (string) $key : self::quote($key);
    }

    private static function string(string $value, int $depth): string
    {
        if (! str_contains($value, "\n")) {
            return self::quote($value);
        }
        if (str_contains($value, "\r")) {
            return self::quote($value);
        }
        // A nowdoc keeps prose readable. Its body is literal, and with the closing marker at
        // column 0 no indentation is stripped; the marker just must not start a line of the text.
        $marker = 'TXT';
        for ($n = 2; preg_match('/^\s*'.$marker.'\b/m', $value); $n++) {
            $marker = "TXT{$n}";
        }

        return "<<<'{$marker}'\n{$value}\n{$marker}";
    }

    private static function quote(string $value): string
    {
        if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\t")) {
            return '"'.addcslashes($value, "\\\"\$\n\r\t").'"';
        }

        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    private static function float(float $value): string
    {
        $s = var_export($value, true);

        return str_contains($s, '.') || str_contains($s, 'E') ? $s : "{$s}.0";
    }
}
