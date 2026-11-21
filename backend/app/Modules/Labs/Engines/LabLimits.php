<?php

namespace App\Modules\Labs\Engines;

/** The bounds every lab action runs within (ARCHITECTURE §7.1). */
final class LabLimits
{
    public const MAX_COMMAND_LENGTH = 500;

    public const MAX_PIPE_STAGES = 5;

    public const MAX_OUTPUT_BYTES = 64 * 1024;

    public const MAX_STATE_BYTES = 256 * 1024;

    public const MAX_PATTERN_LENGTH = 100;

    public const PCRE_BACKTRACK_LIMIT = 10000;

    public const PCRE_RECURSION_LIMIT = 1000;

    public const MAX_ANSWER_LENGTH = 200;

    /** Actions per learner per minute on the action endpoint (`throttle:lab-actions`). */
    public const ACTIONS_PER_MINUTE = 60;

    /** Cuts text to the output limit, on a character boundary, saying so. */
    public static function capOutput(string $text, string $notice): string
    {
        if (strlen($text) <= self::MAX_OUTPUT_BYTES) {
            return $text;
        }

        return mb_strcut($text, 0, self::MAX_OUTPUT_BYTES)."\n".$notice;
    }
}
