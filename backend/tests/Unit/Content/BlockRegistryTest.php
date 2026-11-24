<?php

use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\Blocks\Types\DiagramBlock;
use Illuminate\Validation\ValidationException;

/*
| Contract fixtures (app/Modules/Content/Blocks/fixtures/<type>.{valid,invalid}.json) are
| shared with the frontend's Vitest suite: a block type is only done when both the JSON
| Schema here and the zod schema there agree on the same fixtures.
*/

// Datasets are built before the app boots, so resolve the path from this file, not app_path().
const FIXTURES = __DIR__.'/../../../app/Modules/Content/Blocks/fixtures';
const SOURCE_FIXTURES = __DIR__.'/../../../app/Modules/Content/Documents/fixtures';

$fixtures = fn (string $kind) => collect(glob(FIXTURES.'/*.'.$kind.'.json'))
    ->flatMap(function (string $file) {
        $type = explode('.', basename($file))[0];

        return collect(json_decode(file_get_contents($file), true))
            ->mapWithKeys(fn ($payload, $i) => ["{$type} #{$i}" => [$type, $payload]]);
    })
    ->all();

it('has fixtures for every registered type', function () {
    foreach (array_keys(BlockRegistry::default()->all()) as $type) {
        expect(FIXTURES."/{$type}.valid.json")->toBeFile()
            ->and(FIXTURES."/{$type}.invalid.json")->toBeFile();
    }
});

it('accepts every valid fixture', function (string $type, array $payload) {
    $clean = BlockRegistry::default()->validateSections([['type' => $type, 'heading' => 'H', 'payload' => $payload]]);

    expect($clean[0]['type'])->toBe($type);
})->with($fixtures('valid'));

it('rejects every invalid fixture', function (string $type, array $payload) {
    BlockRegistry::default()->validateSections([['type' => $type, 'payload' => $payload]]);
})->with($fixtures('invalid'))->throws(ValidationException::class);

it('accepts every type\'s own example payload', function () {
    $registry = BlockRegistry::default();
    foreach ($registry->all() as $key => $block) {
        expect(fn () => $registry->validateSections([['type' => $key, 'payload' => $block->example()]]))->not->toThrow(ValidationException::class);
    }
});

it('reports errors at the exact block and field', function () {
    try {
        BlockRegistry::default()->validateSections([
            ['type' => 'prose', 'payload' => ['text' => 'fine']],
            ['type' => 'steps', 'payload' => ['items' => ['ok', 7]]],
            ['type' => 'marquee', 'payload' => []],
            ['type' => 'callout', 'heading' => str_repeat('x', 161), 'payload' => ['text' => 'x', 'tone' => 'info']],
        ]);
        $this->fail('expected a validation error');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toEqualCanonicalizing([
            'sections.1.payload.items.1',
            'sections.2.type',
            'sections.3.heading',
        ]);
    }
});

it('normalizes payloads before validating and storing them', function () {
    $clean = BlockRegistry::default()->validateSections([
        ['type' => 'callout', 'heading' => '  Note  ', 'payload' => ['text' => '  Spaced  ']],
        ['type' => 'code', 'heading' => '', 'payload' => ['code' => "ls\n\n"]],
    ]);

    expect($clean[0])->toBe(['type' => 'callout', 'heading' => 'Note', 'payload' => ['text' => 'Spaced', 'tone' => 'info'], 'sources' => null])
        ->and($clean[1]['heading'])->toBeNull()
        ->and($clean[1]['payload'])->toBe(['code' => 'ls', 'language' => 'text']);
});

it('only allows bespoke diagrams that have a data schema', function () {
    BlockRegistry::default()->validateSections([['type' => 'diagram', 'payload' => ['key' => 'pie-chart', 'data' => ['x' => 1]]]]);
})->throws(ValidationException::class, 'Unknown diagram');

it('keeps the legacy OSI and TCP diagrams valid but stops offering them', function () {
    expect(DiagramBlock::keys())->toBe(['cia-triad'])
        ->and(DiagramBlock::allKeys())->toContain('osi-model', 'tcp-handshake');

    $legacy = ['key' => 'tcp-handshake', 'caption' => null, 'data' => [
        'participants' => ['client' => 'C', 'server' => 'S'],
        'steps' => [['from' => 'client', 'label' => 'SYN', 'fields' => 'seq=1', 'explanation' => 'x', 'states' => ['client' => 'a', 'server' => 'b']]],
    ]];
    expect(fn () => BlockRegistry::default()->validateSections([['type' => 'diagram', 'payload' => $legacy]]))->not->toThrow(ValidationException::class);
});

it('points reference errors in visual blocks at the exact field', function () {
    try {
        BlockRegistry::default()->validateSections([
            ['type' => 'sequence', 'payload' => [
                'actors' => [['id' => 'a', 'name' => 'A'], ['id' => 'b', 'name' => 'B']],
                'messages' => [['from' => 'a', 'to' => 'b', 'label' => 'ok', 'explanation' => 'x'], ['from' => 'a', 'to' => 'c', 'label' => 'x', 'explanation' => 'y']],
            ]],
            ['type' => 'knowledge-check', 'payload' => ['questions' => [
                ['prompt' => 'q', 'options' => [['text' => 'a', 'correct' => false], ['text' => 'b', 'correct' => false]], 'explanation' => 'e'],
            ]]],
        ]);
        $this->fail('expected a validation error');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toEqualCanonicalizing([
            'sections.0.payload.messages.1.to',
            'sections.1.payload.questions.0.options',
        ]);
    }
});

it('trims a calculator starting value and drops an empty one', function () {
    $clean = BlockRegistry::default()->validateSections([
        ['type' => 'calculator', 'payload' => ['tool' => 'cidr', 'initial' => ' 10.0.0.1/8 ']],
        ['type' => 'calculator', 'payload' => ['tool' => 'chmod', 'initial' => '  ']],
    ]);

    expect($clean[0]['payload'])->toBe(['tool' => 'cidr', 'initial' => '10.0.0.1/8'])
        ->and($clean[1]['payload'])->toBe(['tool' => 'chmod']);
});

it('declares in every block schema whether the block states facts, as a boolean or a condition (D69)', function () {
    foreach (BlockRegistry::default()->all() as $key => $block) {
        $rule = $block->schema()->{'x-factual'} ?? null;
        if (is_bool($rule)) {
            continue;
        }
        expect($rule)->toBeObject("{$key} has no x-factual")
            ->and($rule->property ?? null)->toBeString("{$key}: x-factual needs a property")
            ->and($rule->in ?? null)->toBeArray()->not->toBeEmpty()
            // Every value the condition names is one the schema allows, and notes only name those values.
            ->and(array_diff($rule->in, $block->schema()->properties->{$rule->property}->enum ?? $rule->in))->toBe([])
            ->and(array_diff(array_keys((array) ($rule->notes ?? [])), $rule->in))->toBe([]);
    }
});

it('says whether a section states facts: always, never, or when a property has one of some values', function () {
    $registry = BlockRegistry::default();

    expect($registry->statesFacts('prose', ['text' => 'x']))->toBeTrue()
        ->and($registry->statesFacts('code', ['code' => 'ls']))->toBeFalse()
        ->and($registry->statesFacts('callout', ['text' => 'x', 'tone' => 'warning']))->toBeTrue()
        ->and($registry->statesFacts('callout', ['text' => 'x', 'tone' => 'fun-fact']))->toBeTrue()
        ->and($registry->statesFacts('callout', ['text' => 'x', 'tone' => 'recap']))->toBeFalse()
        ->and($registry->statesFacts('callout', ['text' => 'x', 'tone' => 'tip']))->toBeFalse()
        ->and($registry->statesFacts('callout', ['text' => 'x']))->toBeFalse()
        ->and($registry->statesFacts('marquee', []))->toBeTrue()
        ->and($registry->factualNote('callout', ['tone' => 'fun-fact']))->toContain('analogy')
        ->and($registry->factualNote('callout', ['tone' => 'info']))->toBeNull()
        ->and($registry->factualNote('prose', ['text' => 'x']))->toBeNull();
});

/*
| Section sources (D68) are checked once for every block type; the fixtures are shared with
| the zod mirror in frontend/src/features/learning/blocks/schemas.ts.
*/
$sourceFixtures = fn (string $kind) => collect(json_decode(file_get_contents(SOURCE_FIXTURES."/sources.{$kind}.json"), true))
    ->mapWithKeys(fn ($sources, $i) => ["sources #{$i}" => [$sources]])
    ->all();

it('accepts every valid sources fixture on any block', function (array $sources) {
    $clean = BlockRegistry::default()->validateSections([['type' => 'prose', 'payload' => ['text' => 'x'], 'sources' => $sources]]);

    expect($clean[0]['sources'])->toBe($sources === [] ? null : $sources);
})->with($sourceFixtures('valid'));

it('rejects every invalid sources fixture', function (mixed $sources) {
    BlockRegistry::default()->validateSections([['type' => 'layer-stack', 'payload' => BlockRegistry::default()->get('layer-stack')->example(), 'sources' => $sources]]);
})->with($sourceFixtures('invalid'))->throws(ValidationException::class);

it('reports source errors under the section', function () {
    try {
        BlockRegistry::default()->validateSections([
            ['type' => 'prose', 'payload' => ['text' => 'x']],
            ['type' => 'prose', 'payload' => ['text' => 'y'], 'sources' => [['label' => ' RFC 791 ', 'url' => 'https://www.rfc-editor.org/rfc/rfc791', 'kind' => 'blog']]],
        ]);
        $this->fail('expected a validation error');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['sections.1.sources.0.kind']);
    }
});

it('trims source text', function () {
    $clean = BlockRegistry::default()->validateSections([
        ['type' => 'prose', 'payload' => ['text' => 'x'], 'sources' => [['label' => ' RFC 791 ', 'url' => 'https://www.rfc-editor.org/rfc/rfc791 ', 'kind' => 'rfc']]],
    ]);

    expect($clean[0]['sources'])->toBe([['label' => 'RFC 791', 'url' => 'https://www.rfc-editor.org/rfc/rfc791', 'kind' => 'rfc']]);
});
