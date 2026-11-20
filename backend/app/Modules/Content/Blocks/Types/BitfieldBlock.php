<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/** Rows of bits in labelled fields, such as an address and its subnet mask. */
final class BitfieldBlock extends JsonSchemaBlock
{
    public const MAX_ROW_BITS = 64;

    public function key(): string
    {
        return 'bitfield';
    }

    public function label(): string
    {
        return 'Bit field';
    }

    public function description(): string
    {
        return 'Bits split into labelled fields, such as an IPv4 address and its mask.';
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

        foreach ($payload['rows'] as $i => $row) {
            $bits = array_sum(array_map(fn ($f) => strlen($f['bits']), $row['fields']));
            if ($bits > self::MAX_ROW_BITS) {
                $errors["rows.{$i}.fields"][] = __('A row holds at most :max bits (this one has :count).', ['max' => self::MAX_ROW_BITS, 'count' => $bits]);
            }
        }

        return $errors;
    }
}
