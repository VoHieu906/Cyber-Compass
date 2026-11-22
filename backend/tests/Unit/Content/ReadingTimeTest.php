<?php

use App\Modules\Content\Documents\ReadingTime;

it('counts heading and payload words at 200 a minute, rounding up', function () {
    $words = fn (int $n) => implode(' ', array_fill(0, $n, 'word'));

    expect(ReadingTime::minutes([]))->toBe(1)
        ->and(ReadingTime::minutes([['type' => 'prose', 'heading' => null, 'payload' => ['text' => $words(200)]]]))->toBe(1)
        ->and(ReadingTime::minutes([['type' => 'prose', 'heading' => 'Two words', 'payload' => ['text' => $words(200)]]]))->toBe(2)
        ->and(ReadingTime::minutes([['type' => 'steps', 'heading' => null, 'payload' => ['items' => [$words(150), $words(250)]]]]))->toBe(2);
});

it('skips identifiers and script code, counts code by lines and adds time for interactive blocks', function () {
    $sections = [
        ['type' => 'flow', 'heading' => null, 'payload' => ['nodes' => [['id' => 'build-the-image-now', 'label' => 'Build']], 'edges' => [['from' => 'a-b-c', 'to' => 'd-e-f']]]],
        ['type' => 'script-3d', 'heading' => null, 'payload' => ['code' => implode(' ', array_fill(0, 500, 'x')), 'description' => 'One']],
        ['type' => 'code', 'heading' => null, 'payload' => ['code' => "a\nb\nc", 'language' => 'bash']],
        ['type' => 'knowledge-check', 'heading' => null, 'payload' => []],
    ];

    // 1 label + 1 description + 3 code lines = 5 words; two interactive blocks add a minute.
    expect(ReadingTime::minutes($sections))->toBe(2);
});
