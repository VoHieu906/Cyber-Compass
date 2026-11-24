<?php

use App\Enums\LessonStatus;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseGraph;
use App\Models\Lesson;
use App\Models\User;

/*
| Course map suggestions in the admin (F4 Part C, D63): offered beside the issues, never applied by
| the server, and accepted through the normal audited save.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->net = Course::query()->where('slug', 'networking-fundamentals')->firstOrFail();
    $this->netUrl = "/api/v1/admin/content/courses/{$this->net->id}/graph";
});

/** The seeded networking map with arp cut loose: no relations, no place. */
function looseArp(): array
{
    $row = CourseGraph::query()->where('course_id', test()->net->id)->firstOrFail();
    $graph = $row->graph;
    $graph['edges'] = array_values(array_filter($graph['edges'], fn ($e) => $e['from'] !== 'arp' && $e['to'] !== 'arp'));
    unset($graph['placement']['arp']);
    $row->update(['graph' => $graph]);

    return $graph;
}

it('starts a map from the course structure for a course without one, and saves nothing', function () {
    $course = Course::query()->where('slug', 'applied-cryptography')->firstOrFail();

    $data = $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/content/courses/{$course->id}/graph")->assertOk()->json('data');
    $start = collect($data['suggestions'])->firstWhere('type', 'start_map');

    expect($data['graph'])->toBeNull()
        ->and($start['graph']['problem'])->toBe($course->summary)
        ->and($start['graph']['layout'])->toBe('auto')
        ->and($start['graph']['edges'][0])->toBe(['from' => 'hashing-and-passwords', 'to' => 'symmetric-asymmetric-encryption', 'type' => 'builds-on'])
        ->and(collect($data['suggestions'])->where('type', 'problem')->count())->toBe(3)
        ->and(CourseGraph::query()->where('course_id', $course->id)->exists())->toBeFalse();
});

it('suggests a relation and a place for a lesson cut loose from the map', function () {
    looseArp();

    $s = collect($this->actingAs($this->admin, 'web')->getJson($this->netUrl)->assertOk()->json('data.suggestions'));

    expect($s->firstWhere('key', 'relation:arp')['edge'])->toBe(['from' => 'ip-addressing', 'to' => 'arp', 'type' => 'builds-on'])
        ->and($s->firstWhere('key', 'placement:arp')['placement'])->toMatchArray(['lane' => 'addressing'])
        // Every other lesson keeps its architecture and its place, so nothing else is suggested.
        ->and($s->pluck('type')->unique()->sort()->values()->all())->toBe(['placement', 'relation']);
});

it('never suggests a hidden lesson, as a subject or as a neighbour', function () {
    looseArp();
    Lesson::query()->where('slug', 'ip-addressing')->update(['status' => LessonStatus::Draft]);

    $s = collect($this->actingAs($this->admin, 'web')->getJson($this->netUrl)->assertOk()->json('data.suggestions'));

    // arp is now first in its chapter: it requires the last lesson of the chapter before.
    expect($s->firstWhere('key', 'relation:arp')['edge'])->toBe(['from' => 'vlans', 'to' => 'arp', 'type' => 'requires'])
        ->and(json_encode($s->all()))->not->toContain('ip-addressing');
});

it('saves an accepted suggestion like any edit: audited, locked and owned by the database', function () {
    $graph = looseArp();
    $s = collect($this->actingAs($this->admin, 'web')->getJson($this->netUrl)->json('data.suggestions'));
    $graph['edges'][] = $s->firstWhere('key', 'relation:arp')['edge'];
    $graph['placement']['arp'] = $s->firstWhere('key', 'placement:arp')['placement'];

    $data = $this->actingAs($this->admin, 'web')->putJson($this->netUrl, ['graph' => $graph, 'lock_version' => 1])->assertOk()->json('data');

    expect($data['owned_by_admin'])->toBeTrue()
        ->and(collect($data['issues'])->where('lesson', 'arp')->all())->toBe([])
        ->and(collect($data['suggestions'])->where('lesson', 'arp')->all())->toBe([])
        ->and(AdminAuditLog::query()->where('action', 'course_graph.saved')->count())->toBe(1);
    $this->actingAs($this->admin, 'web')->putJson($this->netUrl, ['graph' => $graph, 'lock_version' => 1])->assertStatus(409);
});

it('offers the lesson editor a draft problem from the summary', function () {
    $lesson = Lesson::query()->where('slug', 'tls-overview')->firstOrFail();

    $this->actingAs($this->admin, 'web')->getJson("/api/v1/admin/content/lessons/{$lesson->id}")->assertOk()
        ->assertJsonPath('data.architecture_suggestions.0', ['type' => 'problem', 'resolves' => 'no_problem', 'problem' => $lesson->summary, 'draft' => true]);
});

it('lists suggestions as information in the health check, which still passes', function () {
    looseArp();

    $this->artisan('content:check-graphs')
        ->expectsOutputToContain('networking-fundamentals: suggest relation arp: builds-on from ip-addressing to arp')
        ->assertSuccessful();
});

it('is for admins only', function () {
    $this->actingAs(User::factory()->create(), 'web')->getJson($this->netUrl)->assertForbidden();
});
