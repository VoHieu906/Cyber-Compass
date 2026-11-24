<?php

namespace App\Modules\Content\Blocks;

use App\Modules\Content\Blocks\Types\BitfieldBlock;
use App\Modules\Content\Blocks\Types\CalculatorBlock;
use App\Modules\Content\Blocks\Types\CalloutBlock;
use App\Modules\Content\Blocks\Types\CodeBlock;
use App\Modules\Content\Blocks\Types\ComparisonBlock;
use App\Modules\Content\Blocks\Types\DiagramBlock;
use App\Modules\Content\Blocks\Types\FlowBlock;
use App\Modules\Content\Blocks\Types\KnowledgeCheckBlock;
use App\Modules\Content\Blocks\Types\LayerStackBlock;
use App\Modules\Content\Blocks\Types\MediaBlock;
use App\Modules\Content\Blocks\Types\ProseBlock;
use App\Modules\Content\Blocks\Types\Scene3dBlock;
use App\Modules\Content\Blocks\Types\Script3dBlock;
use App\Modules\Content\Blocks\Types\SequenceBlock;
use App\Modules\Content\Blocks\Types\StepsBlock;
use App\Modules\Content\Blocks\Types\StepThroughBlock;
use App\Modules\Content\Blocks\Types\TimelineBlock;
use App\Modules\Content\Blocks\Types\TopologyBlock;
use App\Modules\Content\Blocks\Types\TreeBlock;
use App\Modules\Content\Documents\SectionSources;
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
            new LayerStackBlock,
            new SequenceBlock,
            new FlowBlock,
            new TreeBlock,
            new TopologyBlock,
            new ComparisonBlock,
            new TimelineBlock,
            new BitfieldBlock,
            new CalculatorBlock,
            new StepThroughBlock,
            new KnowledgeCheckBlock,
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
     * Whether a section states facts and so should cite sources (D68, D69). The block's schema says
     * so in `x-factual`: true, false, or a condition on one payload property,
     * `{"property": "tone", "in": ["info", "warning"], "notes": {"<value>": "..."}}`. No type is
     * named here, so a new block type only declares its own rule. A type without one counts as factual.
     */
    public function statesFacts(string $type, array $payload): bool
    {
        $rule = $this->factualRule($type);

        return is_bool($rule) ? $rule : in_array($payload[$rule->property] ?? null, (array) $rule->in, true);
    }

    /** The schema's note for this payload, when its rule has one (why a fun fact may need no source). */
    public function factualNote(string $type, array $payload): ?string
    {
        $rule = $this->factualRule($type);
        $value = is_bool($rule) ? null : ($payload[$rule->property] ?? null);

        return is_string($value) ? ($rule->notes->{$value} ?? null) : null;
    }

    /** @return bool|object the schema's `x-factual` */
    public function factualRule(string $type): bool|object
    {
        return $this->has($type) ? ($this->get($type)->schema()->{'x-factual'} ?? true) : true;
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

            [$sourceErrors, $sources] = SectionSources::validate($section['sources'] ?? null);
            foreach ($sourceErrors as $path => $messages) {
                $errors[rtrim("{$at}.sources.{$path}", '.')] = $messages;
            }

            $clean[] = [
                'type' => $type,
                'heading' => is_string($heading) && trim($heading) !== '' ? trim($heading) : null,
                'payload' => $payload,
                'sources' => $sources,
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }
}
