<?php

namespace App\Modules\Content\Blocks;

use App\Modules\Content\Blocks\Types\CalloutBlock;
use App\Modules\Content\Blocks\Types\CodeBlock;
use App\Modules\Content\Blocks\Types\DiagramBlock;
use App\Modules\Content\Blocks\Types\MediaBlock;
use App\Modules\Content\Blocks\Types\ProseBlock;
use App\Modules\Content\Blocks\Types\Scene3dBlock;
use App\Modules\Content\Blocks\Types\Script3dBlock;
use App\Modules\Content\Blocks\Types\StepsBlock;
use Illuminate\Validation\ValidationException;

/**
 * The single source of allowed lesson block types and their rules (ARCHITECTURE D17).
 * Everything that writes lesson sections (seeder import, admin console) validates here.
 */
final class BlockRegistry
{
    /** @var array<string, BlockType> */
    private array $types = [];

    /** @param  iterable<BlockType>  $types */
    public function __construct(iterable $types = [])
    {
        foreach ($types as $type) {
            $this->types[$type->key()] = $type;
        }
    }

    public static function default(): self
    {
        return new self([
            new ProseBlock,
            new CalloutBlock,
            new StepsBlock,
            new CodeBlock,
            new DiagramBlock,
            new MediaBlock,
            new Scene3dBlock,
            new Script3dBlock,
        ]);
    }

    /** @return array<string, BlockType> */
    public function all(): array
    {
        return $this->types;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function get(string $key): BlockType
    {
        return $this->types[$key] ?? throw new \InvalidArgumentException("Unknown block type [{$key}].");
    }

    /**
     * Validates an ordered list of sections ({type, heading?, payload}) and returns them
     * normalized. Errors are keyed like the admin API's request body:
     * "sections.3.payload.items.0", so the editor can point at the exact block and field.
     *
     * @param  list<array{type?: mixed, heading?: mixed, payload?: mixed}>  $sections
     * @param  string  $prefix  key prefix for errors (e.g. "sections", or a lesson slug for the seeder)
     * @return list<array{type: string, heading: ?string, payload: array}>
     *
     * @throws ValidationException
     */
    public function validateSections(array $sections, string $prefix = 'sections'): array
    {
        $errors = [];
        $clean = [];

        foreach (array_values($sections) as $i => $section) {
            $at = "{$prefix}.{$i}";
            $type = $section['type'] ?? null;
            $heading = $section['heading'] ?? null;

            if (! is_string($type) || ! $this->has($type)) {
                $errors["{$at}.type"] = ['Unknown block type. Allowed: '.implode(', ', array_keys($this->types))];

                continue;
            }
            if ($heading !== null && (! is_string($heading) || mb_strlen($heading) > 160)) {
                $errors["{$at}.heading"] = ['The heading must be text of at most 160 characters.'];
            }
            if (! is_array($section['payload'] ?? null)) {
                $errors["{$at}.payload"] = ['The payload must be an object.'];

                continue;
            }

            $block = $this->get($type);
            $payload = $block->normalize($section['payload']);
            foreach ($block->validate($payload) as $path => $messages) {
                $errors[rtrim("{$at}.payload.{$path}", '.')] = $messages;
            }

            $clean[] = [
                'type' => $type,
                'heading' => is_string($heading) && trim($heading) !== '' ? trim($heading) : null,
                'payload' => $payload,
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }
}
