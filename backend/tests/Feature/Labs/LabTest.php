<?php

use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Labs\Engines\LabLimits;
use App\Modules\Labs\Events\LabCompleted;
use App\Modules\Labs\Services\LabFiles;
use App\Modules\Labs\Services\LabLoader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/*
| Labs (ARCHITECTURE §7, §7.1, D28): server-side engines, answers kept on the server, optimistic
| locking between tabs, a capped state, a per-user action limit, and completion once.
*/

beforeEach(function () {
    $this->user = User::factory()->create();
});

function startLab(string $slug): array
{
    return test()->actingAs(test()->user, 'web')->postJson("/api/v1/labs/{$slug}/attempts")->assertSuccessful()->json('data');
}

function act(array $attempt, array $action, ?int $version = null): TestResponse
{
    return test()->postJson("/api/v1/lab-attempts/{$attempt['id']}/actions", ['version' => $version ?? $attempt['version'], 'action' => $action]);
}

it('requires a session', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([['GET', '/api/v1/labs'], ['GET', '/api/v1/labs/lock-down-a-web-server'], ['POST', '/api/v1/labs/lock-down-a-web-server/attempts'], ['POST', '/api/v1/lab-attempts/1/actions']]);

it('lists the labs in order with my progress and their lessons', function () {
    $labs = $this->actingAs($this->user, 'web')->getJson('/api/v1/labs')->assertOk()->json('data');

    expect(collect($labs)->pluck('slug')->all())->toBe(['investigate-a-suspicious-login', 'lock-down-a-web-server', 'inspect-a-shop-session'])
        ->and($labs[0])->toMatchArray(['engine' => 'linux-shell', 'lesson' => ['slug' => 'linux-logs', 'title' => Lesson::query()->where('slug', 'linux-logs')->value('title')]])
        ->and($labs[0]['progress'])->toBe(['started' => false, 'completed' => false, 'passed' => 0, 'total' => 5]);
});

it('never sends answers, hidden packets, routes or files', function (string $slug, array $secrets) {
    $body = $this->actingAs($this->user, 'web')->getJson("/api/v1/labs/{$slug}")->assertOk()->getContent();
    $started = $this->postJson("/api/v1/labs/{$slug}/attempts")->getContent();

    foreach ([$body, $started] as $json) {
        expect($json)->not->toContain('"answers"')->not->toContain('"packets"')->not->toContain('"routes"')->not->toContain('"files"');
        foreach ($secrets as $secret) {
            expect($json)->not->toContain($secret);
        }
    }
})->with([
    ['investigate-a-suspicious-login', ['203.0.113.77', '/tmp/.cache/update.sh']],
    ['lock-down-a-web-server', ['203.0.113.50', '3306']],
    ['inspect-a-shop-session', ['minh.tran@shop.example', 'order-1002']],
]);

it('starts once: 201, then 200 with the same attempt', function () {
    $first = $this->actingAs($this->user, 'web')->postJson('/api/v1/labs/lock-down-a-web-server/attempts')->assertCreated()->json('data.attempt');
    $again = $this->postJson('/api/v1/labs/lock-down-a-web-server/attempts')->assertOk()->json('data.attempt');

    expect($again['id'])->toBe($first['id'])->and($first['version'])->toBe(1)
        ->and($first['state']['rules'])->toHaveCount(2);
});

it('runs a command on the server and returns its output and the new version', function () {
    $attempt = startLab('investigate-a-suspicious-login')['attempt'];
    $data = act($attempt, ['type' => 'command', 'command' => 'cat notes.txt'])->assertOk()->json('data');

    expect($data['output']['text'])->toStartWith('Ticket SEC-142')
        ->and($data['attempt']['version'])->toBe(2)
        ->and($data['attempt']['state']['history'][0]['command'])->toBe('cat notes.txt');
});

it('refuses a stale version with 409 and changes nothing (two tabs)', function () {
    $attempt = startLab('investigate-a-suspicious-login')['attempt'];
    act($attempt, ['type' => 'command', 'command' => 'cd /var/log'])->assertOk();

    act($attempt, ['type' => 'command', 'command' => 'cd /tmp'])->assertConflict();
    $row = LabAttempt::query()->findOrFail($attempt['id']);
    expect($row->state_version)->toBe(2)->and($row->state['engine']['cwd'])->toBe('/var/log');
});

it('hides other learners attempts', function () {
    $attempt = startLab('lock-down-a-web-server')['attempt'];
    Auth::forgetGuards();
    $this->actingAs(User::factory()->create(), 'web');

    act($attempt, ['type' => 'set_default', 'policy' => 'deny'])->assertNotFound();
    $this->postJson("/api/v1/lab-attempts/{$attempt['id']}/reset", ['version' => 1])->assertNotFound();
});

it('rejects malformed actions with 422 and a message', function (array $action) {
    $attempt = startLab('lock-down-a-web-server')['attempt'];

    act($attempt, $action)->assertUnprocessable()->assertJsonValidationErrors('action');
    expect(LabAttempt::query()->findOrFail($attempt['id'])->state_version)->toBe(1);
})->with([
    'unknown action' => [['type' => 'format_disk']],
    'bad rule' => [['type' => 'add_rule', 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'source' => 'everyone']]],
    'answer to no question' => [['type' => 'answer', 'checkpoint' => 'nope', 'value' => 'x']],
    'too large' => [['type' => 'test', 'padding' => str_repeat('x', 9000)]],
]);

it('refuses an action once the stored state would pass its cap', function () {
    $attempt = startLab('investigate-a-suspicious-login')['attempt'];
    $row = LabAttempt::query()->findOrFail($attempt['id']);
    $state = $row->state;
    $state['engine']['read'] = array_fill(0, 300, str_repeat('x', 1000));
    $row->update(['state' => $state]);

    act($attempt, ['type' => 'command', 'command' => 'pwd'])->assertUnprocessable()->assertJsonValidationErrors('action');
    expect(strlen(json_encode($row->fresh()->state)))->toBeGreaterThan(LabLimits::MAX_STATE_BYTES);
});

it('limits actions per learner per minute', function () {
    $attempt = startLab('investigate-a-suspicious-login')['attempt'];
    $version = $attempt['version'];
    for ($i = 0; $i < LabLimits::ACTIONS_PER_MINUTE; $i++) {
        $version = act($attempt, ['type' => 'command', 'command' => 'pwd'], $version)->assertOk()->json('data.attempt.version');
    }

    act($attempt, ['type' => 'command', 'command' => 'pwd'], $version)->assertTooManyRequests();
});

it('completes the shell lab once, by commands and answers, and keeps completion through a reset', function () {
    Event::fake([LabCompleted::class]);
    $attempt = startLab('investigate-a-suspicious-login')['attempt'];
    $version = $attempt['version'];
    $step = function (array $action) use ($attempt, &$version) {
        $data = act($attempt, $action, $version)->assertOk()->json('data');
        $version = $data['attempt']['version'];

        return $data;
    };

    $data = $step(['type' => 'command', 'command' => 'grep 203.0.113.77 /var/log/auth.log | grep -c "Failed password"']);
    expect($data['output']['text'])->toBe('24')->and($data['newly_passed'])->toBe(['read-auth-log']);

    expect($step(['type' => 'answer', 'checkpoint' => 'attacker-ip', 'value' => '203.0.113.1'])['output'])->toBe(['checkpoint' => 'attacker-ip', 'correct' => false]);
    $step(['type' => 'answer', 'checkpoint' => 'attacker-ip', 'value' => ' 203.0.113.77 ']);
    $step(['type' => 'answer', 'checkpoint' => 'account', 'value' => 'Deploy']);
    $step(['type' => 'answer', 'checkpoint' => 'failure-count', 'value' => '24']);
    $data = $step(['type' => 'answer', 'checkpoint' => 'backdoor', 'value' => '/tmp/.cache/update.sh']);

    expect(collect($data['checkpoints'])->every(fn ($c) => $c['passed']))->toBeTrue()
        ->and($data['attempt']['completed_at'])->not->toBeNull();
    Event::assertDispatchedTimes(LabCompleted::class, 1);

    $reset = $this->postJson("/api/v1/lab-attempts/{$attempt['id']}/reset", ['version' => $version])->assertOk()->json('data');
    expect($reset['progress'])->toMatchArray(['completed' => true, 'passed' => 0])
        ->and($reset['attempt']['state']['history'])->toBe([]);

    $this->postJson("/api/v1/lab-attempts/{$attempt['id']}/reset", ['version' => $version])->assertConflict();
});

it('completes the firewall and http labs through their engines', function () {
    $fw = startLab('lock-down-a-web-server')['attempt'];
    $v = $fw['version'];
    foreach ([
        ['type' => 'remove_rule', 'index' => 0],
        ['type' => 'add_rule', 'position' => 0, 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.0/24']],
        ['type' => 'add_rule', 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'port' => 443, 'source' => 'any']],
        ['type' => 'set_default', 'policy' => 'deny'],
        ['type' => 'answer', 'checkpoint' => 'default-policy', 'value' => 'Deny'],
    ] as $action) {
        $data = act($fw, $action, $v)->assertOk()->json('data');
        $v = $data['attempt']['version'];
    }
    expect($data['attempt']['completed_at'])->not->toBeNull();

    $http = startLab('inspect-a-shop-session')['attempt'];
    $v = $http['version'];
    $data = act($http, ['type' => 'send', 'request' => ['method' => 'GET', 'path' => '/api/orders/1002', 'cookies' => ['session' => 'u-4821-7f3a9c']]], $v)->assertOk()->json('data');
    expect($data['output']['response']['body'])->toContain('minh.tran@shop.example')
        ->and($data['newly_passed'])->toBe(['other-order']);
});

it('links a lesson only when learners can see it, and the lesson lists its labs', function () {
    expect($this->actingAs($this->user, 'web')->getJson('/api/v1/lessons/linux-logs')->json('data.labs'))
        ->toBe([['slug' => 'investigate-a-suspicious-login', 'title' => 'Investigate a suspicious login']]);

    Lesson::query()->where('slug', 'linux-logs')->update(['status' => 'draft']);
    expect($this->getJson('/api/v1/labs/investigate-a-suspicious-login')->json('data.lesson'))->toBeNull();
});

describe('loader', function () {
    function labFile(): array
    {
        return LabFiles::read()['labs'];
    }

    it('archives a lab that leaves the file, and hides it', function () {
        $file = labFile();
        unset($file['inspect-a-shop-session']);

        expect(app(LabLoader::class)->load($file)['archived'])->toBe(['inspect-a-shop-session'])
            ->and(Lab::query()->where('slug', 'inspect-a-shop-session')->value('is_archived'))->toBeTrue();
        $this->actingAs($this->user, 'web')->getJson('/api/v1/labs/inspect-a-shop-session')->assertNotFound();
    });

    it('validates every lab before writing anything', function () {
        $file = labFile();
        $file['lock-down-a-web-server']['engine'] = 'packet-flow';
        $file['investigate-a-suspicious-login']['scenario']['checkpoints'][1]['answers'] = [];
        $file['inspect-a-shop-session']['scenario']['checkpoints'][2]['route'] = 'nope';
        $file['inspect-a-shop-session']['lesson'] = 'no-such-lesson';

        expect(fn () => app(LabLoader::class)->load($file))->toThrow(InvalidArgumentException::class);
        try {
            app(LabLoader::class)->load($file);
        } catch (InvalidArgumentException $e) {
            expect($e->getMessage())->toContain('engine: Choose one of: linux-shell, firewall-rules, http-inspector')
                ->toContain('scenario.checkpoints.1')
                ->toContain('scenario.checkpoints.2.route: route must be one of the route ids')
                ->toContain('lesson: No lesson has this slug');
        }
    });
});
