<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

final class LayerStackBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'layer-stack';
    }

    public function label(): string
    {
        return 'Layer stack';
    }

    public function description(): string
    {
        return 'Ordered layers (OSI, TCP/IP, defence in depth); learners select a layer for its details.';
    }

    public function editor(): string
    {
        return 'json';
    }
}
