<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\Blocks\BlockType;
use App\Modules\Content\Blocks\Types\DiagramBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class BlockTypeController extends Controller
{
    /**
     * Lesson block types.
     *
     * Every block type the backend accepts, with its JSON Schema, an example payload and
     * which editor the console uses (`form` or `json`). Bespoke diagrams also list their keys.
     */
    public function __invoke(BlockRegistry $registry): JsonResponse
    {
        Gate::authorize('viewAny', Lesson::class);

        return response()->json(['data' => array_values(array_map(fn (BlockType $type) => [
            'key' => $type->key(),
            'label' => $type->label(),
            'description' => $type->description(),
            'editor' => $type->editor(),
            'schema' => $type->schema(),
            'example' => (object) $type->example(),
            ...($type instanceof DiagramBlock ? ['diagram_keys' => DiagramBlock::keys()] : []),
        ], $registry->all()))]);
    }
}
