<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

final class StepThroughBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'step-through';
    }

    public function label(): string
    {
        return 'Step-through';
    }

    public function description(): string
    {
        return 'An annotated walkthrough with parts and values that change from step to step.';
    }

    public function editor(): string
    {
        return 'json';
    }
}
