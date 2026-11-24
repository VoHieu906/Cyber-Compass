<?php

use App\Modules\Content\CourseMap\CourseMapSuggestions;

/*
| Course map suggestions (F4 Part C, D63): the rules on plain data. Lessons are live and in course
| order; `chapter` groups them.
*/

function sgLessons(array $chapters): array
{
    $out = [];
    foreach ($chapters as $chapter => $slugs) {
        foreach ($slugs as $slug) {
            $out[] = ['slug' => $slug, 'chapter' => $chapter];
        }
    }

    return $out;
}

function sgMap(array $edges, array $extra = []): array
{
    return ['version' => 1, 'problem' => 'A big problem.', 'layout' => 'auto', 'edges' => $edges, ...$extra];
}

function sgOfType(array $suggestions, string $type): array
{
    return array_values(array_filter($suggestions, fn ($s) => $s['type'] === $type));
}

function sgEdge(string $from, string $to, string $type = 'builds-on'): array
{
    return ['from' => $from, 'to' => $to, 'type' => $type];
}

describe('relation', function () {
    it('connects a lesson to the one before it in its chapter with builds-on', function () {
        $s = CourseMapSuggestions::find(sgMap([sgEdge('a', 'b')]), sgLessons([1 => ['a', 'b', 'c']]), '');

        expect(sgOfType($s, 'relation'))->toBe([
            ['key' => 'relation:c', 'type' => 'relation', 'lesson' => 'c', 'resolves' => 'unconnected', 'edge' => sgEdge('b', 'c')],
        ]);
    });

    it('connects the first lesson of a chapter to the last lesson of the chapter before with requires', function () {
        $s = CourseMapSuggestions::find(sgMap([sgEdge('a', 'b')]), sgLessons([1 => ['a', 'b'], 2 => ['c', 'd']]), '');

        expect(array_column(sgOfType($s, 'relation'), 'edge'))->toBe([sgEdge('b', 'c', 'requires'), sgEdge('c', 'd')]);
    });

    it('points the first lesson of the first chapter forward', function () {
        $s = CourseMapSuggestions::find(sgMap([sgEdge('b', 'c')]), sgLessons([1 => ['a', 'b', 'c']]), '');

        expect(array_column(sgOfType($s, 'relation'), 'edge'))->toBe([sgEdge('a', 'b')]);
    });

    it('never suggests the same pair twice: a lesson connected by an earlier suggestion gets none', function () {
        $s = CourseMapSuggestions::find(sgMap([sgEdge('c', 'd')]), sgLessons([1 => ['a', 'b', 'c', 'd']]), '');

        // a → b connects both a and b, so b gets no second relation.
        expect(array_column(sgOfType($s, 'relation'), 'edge'))->toBe([sgEdge('a', 'b')]);
    });

    it('refuses an ordering relation that would close a loop, and a duplicate pair', function () {
        $edges = [sgEdge('a', 'b'), sgEdge('b', 'c', 'requires')];

        expect(CourseMapSuggestions::closesLoop(sgEdge('c', 'a'), $edges))->toBeTrue()
            ->and(CourseMapSuggestions::closesLoop(sgEdge('a', 'c'), $edges))->toBeFalse()
            // Contrasts and parallels do not order lessons, so they never close a loop.
            ->and(CourseMapSuggestions::closesLoop(sgEdge('c', 'a', 'contrasts-with'), $edges))->toBeFalse();
    });

    it('accepting every suggestion never leaves a duplicate pair or a loop', function () {
        $chapters = [1 => ['a', 'b', 'c'], 2 => ['d', 'e'], 3 => ['f', 'g', 'h']];
        $graph = sgMap([sgEdge('c', 'a', 'feeds-into'), sgEdge('e', 'd'), sgEdge('h', 'g', 'requires')]);
        $s = CourseMapSuggestions::find($graph, sgLessons($chapters), '');

        $edges = [...$graph['edges'], ...array_column(sgOfType($s, 'relation'), 'edge')];
        $pairs = array_map(fn ($x) => implode('|', [min($x['from'], $x['to']), max($x['from'], $x['to'])]), $edges);
        expect($pairs)->toBe(array_values(array_unique($pairs)));
        foreach ($edges as $i => $edge) {
            $others = array_values(array_filter($edges, fn ($_, $j) => $j !== $i, ARRAY_FILTER_USE_BOTH));
            expect(CourseMapSuggestions::closesLoop($edge, $others))->toBeFalse();
        }
        // Every lesson is connected afterwards.
        expect(array_diff(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], [...array_column($edges, 'from'), ...array_column($edges, 'to')]))->toBe([]);
    });

    it('suggests nothing for a course of one lesson or none', function () {
        expect(CourseMapSuggestions::find(sgMap([]), sgLessons([1 => ['a']]), ''))->toBe([])
            ->and(CourseMapSuggestions::find(sgMap([]), [], ''))->toBe([])
            ->and(CourseMapSuggestions::find(null, [], 'Summary'))->toBe([]);
    });
});

describe('placement', function () {
    $manual = fn (array $placement, array $lanes = [['id' => 'one', 'label' => 'One'], ['id' => 'two', 'label' => 'Two']]) => sgMap(
        [sgEdge('a', 'b'), sgEdge('b', 'c'), sgEdge('c', 'd')],
        ['layout' => 'manual', 'lanes' => $lanes, 'placement' => $placement],
    );

    it('puts a lesson in the lane most of its chapter sits in, in the first free cell after the lesson before it', function () use ($manual) {
        $s = CourseMapSuggestions::find($manual([
            'a' => ['col' => 0, 'row' => 0, 'lane' => 'two'],
            'b' => ['col' => 1, 'row' => 0, 'lane' => 'one'],
            'c' => ['col' => 2, 'row' => 0, 'lane' => 'one'],
        ]), sgLessons([1 => ['a', 'b', 'c', 'd']]), '');

        expect(sgOfType($s, 'placement'))->toBe([
            ['key' => 'placement:d', 'type' => 'placement', 'lesson' => 'd', 'resolves' => 'unplaced', 'placement' => ['col' => 3, 'row' => 0, 'lane' => 'one']],
        ]);
    });

    it('skips taken cells, wraps to the next row and stays inside the 8 by 10 grid', function () use ($manual) {
        $full = [];
        foreach (range(0, 9) as $row) {
            foreach (range(0, 7) as $col) {
                $full["x{$row}-{$col}"] = ['col' => $col, 'row' => $row, 'lane' => 'one'];
            }
        }
        $lessons = [...sgLessons([1 => ['a', 'b']]), ...array_map(fn ($slug) => ['slug' => $slug, 'chapter' => 2], array_keys($full))];

        $row0 = $manual(['a' => ['col' => 7, 'row' => 0, 'lane' => 'one']]);
        expect(sgOfType(CourseMapSuggestions::find($row0, sgLessons([1 => ['a', 'b']]), ''), 'placement')[0]['placement'])
            ->toBe(['col' => 0, 'row' => 1, 'lane' => 'one']);

        // Lane one is full: chapter 1 sits there, so no cell is left and nothing is suggested.
        $graph = $manual(['a' => ['col' => 0, 'row' => 0, 'lane' => 'one'], ...array_slice($full, 1)]);
        expect(sgOfType(CourseMapSuggestions::find($graph, $lessons, ''), 'placement'))->toBe([]);
    });

    it('never adds a lane: a lane the map does not have becomes no lane', function () use ($manual) {
        $graph = $manual(['a' => ['col' => 0, 'row' => 0, 'lane' => 'gone']], [['id' => 'one', 'label' => 'One']]);

        expect(sgOfType(CourseMapSuggestions::find($graph, sgLessons([1 => ['a', 'b']]), ''), 'placement')[0]['placement'])
            ->toBe(['col' => 0, 'row' => 0]);
    });

    it('suggests no placement on the automatic layout', function () {
        expect(sgOfType(CourseMapSuggestions::find(sgMap([]), sgLessons([1 => ['a', 'b']]), ''), 'placement'))->toBe([]);
    });
});

describe('a course with no map', function () {
    it('starts one from the course structure: the summary, the automatic layout and the relation chain', function () {
        $s = CourseMapSuggestions::find(null, sgLessons([1 => ['a', 'b'], 2 => ['c']]), 'Keep data safe.');

        expect(sgOfType($s, 'start_map'))->toBe([['key' => 'start_map', 'type' => 'start_map', 'graph' => [
            'version' => 1, 'problem' => 'Keep data safe.', 'layout' => 'auto', 'edges' => [sgEdge('a', 'b'), sgEdge('b', 'c', 'requires')],
        ]]]);
    });
});

describe('lesson suggestions', function () {
    it('drafts the problem from the summary while the architecture has none', function () {
        $long = str_repeat('word ', 40);

        expect(CourseMapSuggestions::forLesson('Store passwords safely.', [], null))
            ->toBe([['type' => 'problem', 'resolves' => 'no_problem', 'problem' => 'Store passwords safely.', 'draft' => true]])
            ->and(mb_strlen(CourseMapSuggestions::forLesson($long, [], null)[0]['problem']))->toBeLessThanOrEqual(140)
            ->and(CourseMapSuggestions::forLesson('Summary.', [], ['problem' => 'Written already.', 'shape' => 'layers']))->toBe([])
            ->and(CourseMapSuggestions::forLesson('', [], null))->toBe([]);
    });

    it('lists a shape for every block that has one, a looping flow as a cycle, and nothing when none match', function () {
        $sections = [
            ['uuid' => 'u1', 'type' => 'prose', 'heading' => 'Intro', 'payload' => []],
            ['uuid' => 'u2', 'type' => 'layer-stack', 'heading' => 'Layers', 'payload' => []],
            ['uuid' => 'u3', 'type' => 'flow', 'heading' => 'Steps', 'payload' => ['edges' => [sgEdge('x', 'y'), sgEdge('y', 'z')]]],
            ['uuid' => 'u4', 'type' => 'flow', 'heading' => 'Lease', 'payload' => ['edges' => [sgEdge('x', 'y'), sgEdge('y', 'z'), sgEdge('z', 'x')]]],
            ['uuid' => 'u5', 'type' => 'tree', 'heading' => 'Names', 'payload' => []],
            ['uuid' => 'u6', 'type' => 'topology', 'heading' => 'Net', 'payload' => []],
            ['uuid' => 'u7', 'type' => 'comparison', 'heading' => 'Versus', 'payload' => []],
        ];
        $shapes = CourseMapSuggestions::forLesson(null, $sections, null);

        expect(array_map(fn ($s) => [$s['section'], $s['shape']], $shapes))->toBe([
            ['u2', 'layers'], ['u3', 'pipeline'], ['u4', 'cycle'], ['u5', 'tree'], ['u6', 'mesh'], ['u7', 'matrix'],
        ])
            ->and(CourseMapSuggestions::forLesson(null, [$sections[0]], null))->toBe([])
            // A lesson that already has a shape is left alone, and so is one whose author wrote a problem and chose no shape.
            ->and(CourseMapSuggestions::forLesson(null, $sections, ['problem' => 'P.', 'shape' => 'hub']))->toBe([])
            ->and(CourseMapSuggestions::forLesson(null, $sections, ['problem' => 'P.', 'shape' => null]))->toBe([])
            ->and(CourseMapSuggestions::forLesson(null, $sections, ['problem' => '', 'shape' => null]))->toHaveCount(6);
    });
});
