<?php

namespace App\Enums;

/**
 * Assessment bands (ARCHITECTURE §6.1): gap below 40, developing 40 to 74, strength from 75.
 * Bands are taken from the rounded percentage the learner sees, so the label always matches the number.
 */
enum ScoreBand: string
{
    case Gap = 'gap';
    case Developing = 'developing';
    case Strength = 'strength';

    public const DEVELOPING_FROM = 40;

    public const STRENGTH_FROM = 75;

    public static function forPercent(int $percent): self
    {
        return match (true) {
            $percent >= self::STRENGTH_FROM => self::Strength,
            $percent >= self::DEVELOPING_FROM => self::Developing,
            default => self::Gap,
        };
    }
}
