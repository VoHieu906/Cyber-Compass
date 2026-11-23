<?php

use App\Enums\LessonStatus;
use App\Models\Career;
use App\Models\Domain;
use App\Models\Lesson;
use App\Models\Topic;
use App\Modules\Roadmap\Services\RoadmapGenerator;

/** Plans a roadmap and returns "stage slug recommendation reason" lines, in order. */
function planFor(Career $career, ?array $scores): array
{
    return array_map(
        fn (array $n) => sprintf('%d %s %s %s', $n['stage'], Topic::query()->find($n['topic_id'])->slug, $n['recommendation'], $n['reason_key']),
        app(RoadmapGenerator::class)->plan($career, $scores),
    );
}

/** A career whose template is the given topic slugs, all in one stage. */
function careerWith(array $topicSlugs): Career
{
    $career = Career::query()->create([
        'slug' => 'test-career-'.uniqid(), 'title' => 'Test career', 'summary' => 's', 'description' => 'd',
        'responsibilities' => [], 'is_published' => true,
    ]);
    foreach ($topicSlugs as $i => $slug) {
        $career->topics()->attach(Topic::query()->where('slug', $slug)->value('id'), ['stage' => 1, 'position' => $i + 1]);
    }

    return $career;
}

it('makes every template topic required, in template order, without an assessment', function () {
    $career = Career::query()->where('slug', 'soc-analyst')->firstOrFail();

    expect(planFor($career, null))->toBe([
        '1 security-principles required career',
        '1 networking-basics required career',
        '1 linux-shell required career',
        '2 addressing-and-services required career',
        '2 local-networks required career',
        '2 linux-investigation required career',
        '2 os-internals required career',
        '3 routing-and-transport required career',
        '3 security-controls required career',
        '3 python-scripting required career',
        '3 web-basics required career',
    ]);
});

it('marks topics of a strength domain for review, up to intermediate level', function () {
    $career = careerWith(['linux-shell', 'linux-investigation']);
    Topic::query()->where('slug', 'linux-investigation')->update(['level' => 3]);

    expect(planFor($career, ['linux' => 75]))->toBe([
        '1 linux-shell review strength',
        '1 linux-investigation required career', // level 3: not skippable
    ]);
});

it('keeps developing domains required for the career', function () {
    expect(planFor(careerWith(['linux-shell']), ['linux' => 74]))->toBe(['1 linux-shell required career']);
    expect(planFor(careerWith(['linux-shell']), ['linux' => 40]))->toBe(['1 linux-shell required career']);
});

it('inserts foundations of a gap domain before the topic that needs them, prerequisites first', function () {
    $topic = Topic::query()->create(['domain_id' => Domain::query()->where('slug', 'networking')->value('id'), 'slug' => 'network-forensics', 'title' => 'Network forensics', 'summary' => 's', 'level' => 2]);
    $topic->lessons()->attach(Lesson::query()->where('slug', 'dns-and-dhcp')->value('id'), ['position' => 1, 'is_required' => true]);
    $career = careerWith(['network-forensics']);

    // networking-basics, addressing-and-services and local-networks are the level-1 networking
    // topics; the other two require networking-basics.
    expect(planFor($career, ['networking' => 39]))->toBe([
        '1 networking-basics required foundation',
        '1 addressing-and-services required foundation',
        '1 local-networks required foundation',
        '1 network-forensics required gap',
    ]);

    $nodes = app(RoadmapGenerator::class)->plan($career, ['networking' => 39]);
    expect($nodes[0]['reason_params'])->toEqual(['topic' => 'Network forensics', 'domain' => 'Networking'])
        ->and($nodes[3]['reason_params'])->toEqual(['domain' => 'Networking', 'score' => 39])
        ->and(array_column($nodes, 'position'))->toBe([1, 2, 3, 4]);
});

it('does not insert anything for a gap in a foundation-level topic', function () {
    expect(planFor(careerWith(['linux-shell']), ['linux' => 10]))->toBe(['1 linux-shell required gap']);
});

it('never places a topic twice', function () {
    // linux-shell is both a foundation for linux-investigation and in the template itself.
    expect(planFor(careerWith(['linux-investigation', 'linux-shell']), ['linux' => 0]))->toBe([
        '1 linux-shell required foundation',
        '1 linux-investigation required gap',
    ]);
});

it('leaves out topics with no lesson a learner can see', function () {
    $lessonIds = Topic::query()->where('slug', 'linux-shell')->firstOrFail()->lessons()->pluck('lessons.id');
    Lesson::query()->whereIn('id', $lessonIds)->update(['status' => LessonStatus::Archived]);

    expect(planFor(careerWith(['linux-shell', 'linux-investigation']), ['linux' => 0]))->toBe(['1 linux-investigation required gap']);
});
