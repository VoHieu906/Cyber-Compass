<?php

namespace App\Modules\Labs\Engines;

/**
 * Learner-supplied regular expressions, bounded so a pathological pattern cannot hang a worker
 * (ARCHITECTURE §7.1, ReDoS): at most 100 characters, and every match runs with lowered PCRE
 * backtrack and recursion limits, restored afterwards. A pattern that does not compile or hits
 * a limit is reported, never retried.
 */
final class SafeRegex
{
    private function __construct(private readonly string $compiled) {}

    /** @return self|string the matcher, or a message saying why the pattern was refused */
    public static function compile(string $pattern, bool $ignoreCase): self|string
    {
        if (mb_strlen($pattern) > LabLimits::MAX_PATTERN_LENGTH) {
            return 'pattern too long (at most '.LabLimits::MAX_PATTERN_LENGTH.' characters)';
        }
        $compiled = '/'.str_replace('/', '\/', $pattern).'/u'.($ignoreCase ? 'i' : '');
        $ok = self::bounded(fn () => @preg_match($compiled, ''));
        if ($ok === false) {
            return 'invalid pattern';
        }

        return new self($compiled);
    }

    /** @throws LabActionRejected when the pattern hits a PCRE limit on this subject */
    public function matches(string $subject): bool
    {
        $result = self::bounded(fn () => @preg_match($this->compiled, $subject));
        if ($result === false) {
            throw new LabActionRejected('pattern too complex');
        }

        return $result === 1;
    }

    /**
     * Keys of the lines that match (or, inverted, that do not), all under one set of limits.
     *
     * @param  array<int, string>  $lines
     * @return list<int>
     *
     * @throws LabActionRejected when the pattern hits a PCRE limit
     */
    public function matchingKeys(array $lines, bool $invert = false): array
    {
        $keys = [];
        $failed = false;
        self::bounded(function () use ($lines, $invert, &$keys, &$failed) {
            foreach ($lines as $key => $line) {
                $result = @preg_match($this->compiled, $line);
                if ($result === false) {
                    $failed = true;

                    return 0;
                }
                if (($result === 1) !== $invert) {
                    $keys[] = $key;
                }
            }

            return 1;
        });
        if ($failed) {
            throw new LabActionRejected('pattern too complex');
        }

        return $keys;
    }

    private static function bounded(callable $run): int|false
    {
        $backtrack = ini_get('pcre.backtrack_limit');
        $recursion = ini_get('pcre.recursion_limit');
        $jit = ini_get('pcre.jit');
        ini_set('pcre.backtrack_limit', (string) LabLimits::PCRE_BACKTRACK_LIMIT);
        ini_set('pcre.recursion_limit', (string) LabLimits::PCRE_RECURSION_LIMIT);
        // The JIT ignores the backtrack limit, so it is off while learner patterns run.
        ini_set('pcre.jit', '0');
        try {
            return $run();
        } finally {
            ini_set('pcre.backtrack_limit', (string) $backtrack);
            ini_set('pcre.recursion_limit', (string) $recursion);
            ini_set('pcre.jit', (string) $jit);
        }
    }
}
