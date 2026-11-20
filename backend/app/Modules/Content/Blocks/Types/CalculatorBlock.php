<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/**
 * A small tool the learner tries in the browser. The logic is client-side only
 * (frontend/src/features/learning/blocks/calculators.ts); nothing typed is sent anywhere.
 */
final class CalculatorBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'calculator';
    }

    public function label(): string
    {
        return 'Calculator';
    }

    public function description(): string
    {
        return 'A tool learners try themselves: subnet (CIDR), chmod permissions or password strength.';
    }

    public function normalize(array $payload): array
    {
        if (is_string($payload['initial'] ?? null)) {
            $payload['initial'] = trim($payload['initial']);
            if ($payload['initial'] === '') {
                unset($payload['initial']);
            }
        }

        return $payload;
    }

    public function validate(array $payload): array
    {
        $errors = parent::validate($payload);
        if ($errors !== [] || ! isset($payload['initial'])) {
            return $errors;
        }

        $ok = match ($payload['tool']) {
            'cidr' => self::isCidr($payload['initial']),
            'chmod' => preg_match('/^([0-7]{3,4}|[r-][w-][xsS-][r-][w-][xsS-][r-][w-][xtT-])$/', $payload['initial']) === 1,
            default => true,
        };
        if (! $ok) {
            $errors['initial'][] = $payload['tool'] === 'cidr'
                ? __('Use an address with a prefix, like 192.168.1.10/24.')
                : __('Use octal digits like 754, or letters like rwxr-xr--.');
        }

        return $errors;
    }

    private static function isCidr(string $value): bool
    {
        if (preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/(\d{1,2})$#', $value, $m) !== 1) {
            return false;
        }

        return max(array_map('intval', array_slice($m, 1, 4))) <= 255 && (int) $m[5] <= 32;
    }
}
