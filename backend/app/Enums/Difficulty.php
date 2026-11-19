<?php

namespace App\Enums;

enum Difficulty: int
{
    case Beginner = 1;
    case Intermediate = 2;
    case Advanced = 3;

    public function slug(): string
    {
        return strtolower($this->name);
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }
}
