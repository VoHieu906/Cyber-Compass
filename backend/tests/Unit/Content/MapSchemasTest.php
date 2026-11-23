<?php

use App\Modules\Content\CourseMap\MapSchemas;
use App\Modules\Content\CourseMap\MapVersions;

/*
| Course map and lesson architecture validation (F3, D57, D59). The fixtures are shared with
| the zod mirror in the frontend (lib/graph/shapes), so both sides agree on every shape.
*/

$dir = dirname(__DIR__, 3).'/app/Modules/Content/CourseMap';
$fixture = fn (string $name) => json_decode(file_get_contents("{$dir}/fixtures/{$name}.json"), true);
$arch = fn (array $over = []) => [...['version' => 1, 'problem' => 'Why it matters.', 'shape' => 'pipeline', 'nodes' => [
    ['id' => 'a', 'label' => 'First'], ['id' => 'b', 'label' => 'Second'],
], 'edges' => []], ...$over];

it('has a schema and both fixtures for every shape, and nothing else', function () use ($dir) {
    $schemas = array_map(fn ($f) => basename($f, '.json'), glob("{$dir}/schemas/shapes/*.json"));
    sort($schemas);
    $shapes = MapSchemas::SHAPES;
    sort($shapes);

    expect($schemas)->toBe($shapes);
    foreach (MapSchemas::SHAPES as $shape) {
        expect(file_exists("{$dir}/fixtures/{$shape}.valid.json"))->toBeTrue()
            ->and(file_exists("{$dir}/fixtures/{$shape}.invalid.json"))->toBeTrue();
    }
});

it('accepts every valid shape fixture at its maximum size', function (string $shape) use ($fixture) {
    [$errors, $clean] = MapSchemas::architecture($fixture("{$shape}.valid"), []);

    expect($errors)->toBe([])->and($clean['shape'])->toBe($shape);
})->with(MapSchemas::SHAPES);

it('rejects every invalid shape fixture', function (string $shape) use ($fixture) {
    [$errors] = MapSchemas::architecture($fixture("{$shape}.invalid"), []);

    expect($errors)->not->toBe([]);
})->with(MapSchemas::SHAPES);

it('accepts the valid course graph fixture and rejects the invalid one at their fields', function () use ($fixture) {
    expect(MapSchemas::courseGraph($fixture('course-graph.valid'))[0])->toBe([]);

    [$errors] = MapSchemas::courseGraph($fixture('course-graph.invalid'));
    expect($errors)->toHaveKeys(['graph.problem', 'graph.layout', 'graph.placement.lesson-a.col', 'graph.edges.0.type']);
});

it('checks ids, edges and the sections of the lesson', function () use ($arch) {
    $uuid = '6f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30';
    [$errors] = MapSchemas::architecture($arch([
        'nodes' => [['id' => 'a', 'label' => 'A', 'section' => $uuid], ['id' => 'a', 'label' => 'B', 'section' => strtoupper($uuid)]],
        'edges' => [['from' => 'a', 'to' => 'zz'], ['from' => 'a', 'to' => 'a']],
    ]), [$uuid]);

    expect($errors)->toHaveKeys(['architecture.nodes.1.id', 'architecture.edges.0.to', 'architecture.edges.1.to'])
        ->not->toHaveKey('architecture.nodes.0.section')
        ->not->toHaveKey('architecture.nodes.1.section');

    [$errors] = MapSchemas::architecture($arch(['nodes' => [['id' => 'a', 'label' => 'A', 'section' => $uuid], ['id' => 'b', 'label' => 'B']]]), []);
    expect($errors['architecture.nodes.0.section'][0])->toContain('not in the lesson');
});

it('keeps shape-only fields to their shape', function () use ($arch) {
    [$errors] = MapSchemas::architecture($arch(['nodes' => [['id' => 'a', 'label' => 'A', 'parent' => 'b'], ['id' => 'b', 'label' => 'B']], 'rows' => ['x']]), []);

    expect($errors)->toHaveKeys(['architecture.nodes.0'])
        ->and(MapSchemas::architecture($arch(['rows' => ['x']]), [])[0])->toHaveKey('architecture.rows');
});

it('allows a problem without a shape, but not nodes without one', function () use ($arch) {
    expect(MapSchemas::architecture(['version' => 1, 'problem' => 'Only a problem.', 'shape' => null, 'nodes' => [], 'edges' => []], [])[0])->toBe([])
        ->and(MapSchemas::architecture($arch(['shape' => null]), [])[0])->toHaveKey('architecture.shape');
});

it('needs one tree root and no parent loops', function () {
    $tree = fn (array $nodes) => MapSchemas::architecture(['version' => 1, 'problem' => 'P', 'shape' => 'tree', 'nodes' => $nodes, 'edges' => []], [])[0];

    expect($tree([['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']]))->toHaveKey('architecture.nodes')
        ->and($tree([['id' => 'r', 'label' => 'R'], ['id' => 'a', 'label' => 'A', 'parent' => 'b'], ['id' => 'b', 'label' => 'B', 'parent' => 'a']]))->toHaveKey('architecture.nodes.1.parent')
        ->and($tree([['id' => 'r', 'label' => 'R'], ['id' => 'a', 'label' => 'A', 'parent' => 'nope']]))->toHaveKey('architecture.nodes.1.parent');
});

it('keeps matrix nodes inside the grid, one per cell', function () {
    $matrix = fn (array $cells) => MapSchemas::architecture([
        'version' => 1, 'problem' => 'P', 'shape' => 'matrix', 'rows' => ['r1'], 'cols' => ['left', 'right'], 'edges' => [],
        'nodes' => array_map(fn ($c, $i) => ['id' => "n{$i}", 'label' => 'N', 'cell' => $c], $cells, array_keys($cells)),
    ], [])[0];

    expect($matrix([[0, 0], [0, 1]]))->toBe([])
        ->and($matrix([[0, 0], [0, 0]]))->toHaveKey('architecture.nodes.1.cell')
        ->and($matrix([[0, 0], [1, 0]]))->toHaveKey('architecture.nodes.1.cell');
});

it('checks lesson references only when the caller knows the lessons', function () use ($fixture) {
    $graph = $fixture('course-graph.valid');

    expect(MapSchemas::courseGraph($graph)[0])->toBe([])
        ->and(MapSchemas::courseGraph($graph, ['lesson-a', 'lesson-b'])[0])->toHaveKeys(['graph.placement.lesson-c', 'graph.edges.1.to']);

    $graph['placement']['lesson-a']['lane'] = 'nowhere';
    $graph['edges'][] = $graph['edges'][0];
    expect(MapSchemas::courseGraph($graph)[0])->toHaveKeys(['graph.placement.lesson-a.lane', 'graph.edges.3']);
});

it('needs a version this release understands', function () use ($arch) {
    expect(MapSchemas::architecture($arch(['version' => null]), [])[0])->toHaveKey('architecture.version')
        ->and(MapSchemas::architecture($arch(['version' => MapVersions::CURRENT + 1]), [])[0]['architecture.version'][0])->toContain('newer');
});

it('enforces the budgets', function () use ($arch) {
    $nodes = array_map(fn ($i) => ['id' => "n{$i}", 'label' => 'N'], range(1, 13));

    expect(MapSchemas::architecture($arch(['shape' => 'mesh', 'nodes' => $nodes]), [])[0])->not->toBe([])
        ->and(MapSchemas::architecture($arch(['problem' => str_repeat('x', 141)]), [])[0])->toHaveKey('architecture.problem');

    $edges = array_map(fn ($i) => ['from' => "a{$i}", 'to' => "b{$i}", 'type' => 'requires'], range(1, 81));
    expect(MapSchemas::courseGraph(['version' => 1, 'problem' => 'P', 'layout' => 'auto', 'edges' => $edges])[0])->toHaveKey('graph.edges');
});
