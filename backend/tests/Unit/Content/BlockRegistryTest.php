<?php

use App\Modules\Content\Blocks\BlockRegistry;
use Illuminate\Validation\ValidationException;

/*
| Contract fixtures (app/Modules/Content/Blocks/fixtures/<type>.{valid,invalid}.json) are
| shared with the frontend's Vitest suite: a block type is only done when both the JSON
| Schema here and the zod schema there agree on the same fixtures.
*/

// Datasets are built before the app boots, so resolve the path from this file, not app_path().
const FIXTURES = __DIR__.'/../../../app/Modules/Content/Blocks/fixtures';

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

    expect($clean[0])->toBe(['type' => 'callout', 'heading' => 'Note', 'payload' => ['text' => 'Spaced', 'tone' => 'info']])
        ->and($clean[1]['heading'])->toBeNull()
        ->and($clean[1]['payload'])->toBe(['code' => 'ls', 'language' => 'text']);
});

it('only allows bespoke diagrams that have a data schema', function () {
    BlockRegistry::default()->validateSections([['type' => 'diagram', 'payload' => ['key' => 'pie-chart', 'data' => ['x' => 1]]]]);
})->throws(ValidationException::class, 'Unknown diagram');
