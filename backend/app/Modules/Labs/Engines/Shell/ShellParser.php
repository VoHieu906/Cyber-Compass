<?php

namespace App\Modules\Labs\Engines\Shell;

use App\Modules\Labs\Engines\LabLimits;

/**
 * Splits a command line into pipeline stages of words (ARCHITECTURE §7.1). Only words, quotes
 * and `|` exist: redirects, `;`, `&&`, `||`, `&`, backticks, `$()`, `${}` and subshells are
 * refused with a message a learner can act on, never an exception. There is no expansion:
 * `$HOME` and globs are plain text (except `find -name`, which matches its own pattern).
 */
final class ShellParser
{
    /** Characters refused outside single quotes, with what they would have done. */
    private const REFUSED = [
        ';' => 'chaining commands with ";"',
        '&' => 'background jobs and "&&"',
        '>' => 'redirecting output with ">"',
        '<' => 'redirecting input with "<"',
        '`' => 'command substitution with backticks',
        '$' => 'variables and "$(...)"',
        '(' => 'subshells',
        ')' => 'subshells',
        '{' => 'brace expansion',
        '}' => 'brace expansion',
    ];

    /** @return array{stages: list<list<string>>}|array{error: string} */
    public function parse(string $line): array
    {
        if (strlen($line) > LabLimits::MAX_COMMAND_LENGTH) {
            return ['error' => 'command too long (at most '.LabLimits::MAX_COMMAND_LENGTH.' characters)'];
        }

        $stages = [[]];
        $word = null;
        $quote = null;
        $chars = mb_str_split($line);
        foreach ($chars as $i => $c) {
            if ($quote === "'") {
                $quote = $c === "'" ? null : $quote;
                if ($c !== "'") {
                    $word .= $c;
                }

                continue;
            }
            if ($quote === '"') {
                if ($c === '"') {
                    $quote = null;
                } elseif ($c === '$' || $c === '`') {
                    return ['error' => $this->refusal($c)];
                } else {
                    $word .= $c;
                }

                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
                $word ??= '';

                continue;
            }
            if ($c === '|') {
                if (($chars[$i + 1] ?? null) === '|') {
                    return ['error' => 'this lab shell does not support "||". Run one command at a time.'];
                }
                if ($word !== null) {
                    $stages[array_key_last($stages)][] = $word;
                    $word = null;
                }
                if ($stages[array_key_last($stages)] === []) {
                    return ['error' => 'syntax error near "|"'];
                }
                $stages[] = [];

                continue;
            }
            if (isset(self::REFUSED[$c])) {
                return ['error' => $this->refusal($c)];
            }
            if (ctype_space($c)) {
                if ($word !== null) {
                    $stages[array_key_last($stages)][] = $word;
                    $word = null;
                }

                continue;
            }
            $word .= $c;
        }

        if ($quote !== null) {
            return ['error' => 'unclosed quote'];
        }
        if ($word !== null) {
            $stages[array_key_last($stages)][] = $word;
        }
        if (count($stages) > 1 && end($stages) === []) {
            return ['error' => 'syntax error: the pipe has nothing after it'];
        }
        if (count($stages) > LabLimits::MAX_PIPE_STAGES) {
            return ['error' => 'too many pipe stages (at most '.LabLimits::MAX_PIPE_STAGES.')'];
        }

        return ['stages' => $stages === [[]] ? [] : $stages];
    }

    private function refusal(string $char): string
    {
        return 'this lab shell does not support '.self::REFUSED[$char].'. Run one command at a time, and use | to pass output to the next command.';
    }
}
