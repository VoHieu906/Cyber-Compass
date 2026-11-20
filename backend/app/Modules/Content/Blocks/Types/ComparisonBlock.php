<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/** Side-by-side attributes, rendered as a real table (and as cards on narrow screens). */
final class ComparisonBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'comparison';
    }

    public function label(): string
    {
        return 'Comparison';
    }

    public function description(): string
    {
        return 'Two to four things compared attribute by attribute.';
    }

    public function validate(array $payload): array
    {
        $errors = parent::validate($payload);
        if ($errors !== []) {
            return $errors;
        }

        $columns = count($payload['columns']);
        foreach ($payload['rows'] as $i => $row) {
            if (count($row['values']) !== $columns) {
                $errors["rows.{$i}.values"][] = __('Give one value per column (:count).', ['count' => $columns]);
            }
        }

        return $errors;
    }
}
