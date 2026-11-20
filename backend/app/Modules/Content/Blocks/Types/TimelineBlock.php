<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

final class TimelineBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'timeline';
    }

    public function label(): string
    {
        return 'Timeline';
    }

    public function description(): string
    {
        return 'Events in order, each with a time and an optional detail.';
    }
}
