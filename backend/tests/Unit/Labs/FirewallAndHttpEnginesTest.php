<?php

use App\Modules\Labs\Engines\FirewallRulesEngine;
use App\Modules\Labs\Engines\HttpInspectorEngine;
use App\Modules\Labs\Engines\LabActionRejected;

/*
| The firewall and HTTP inspector engines (ARCHITECTURE §7): first-match rules with CIDR
| sources, and a simulated app answering only from its scenario.
*/

describe('firewall rules', function () {
    beforeEach(function () {
        $this->engine = new FirewallRulesEngine;
        $this->scenario = [
            'server' => ['name' => 'web-01', 'ip' => '10.0.20.10'],
            'default' => 'allow',
            'rules' => [['action' => 'allow', 'proto' => 'tcp', 'port' => 22, 'source' => 'any']],
            'checkpoints' => [['id' => 'ssh', 'kind' => 'traffic', 'title' => 'SSH from admins only', 'packets' => [
                ['proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.5', 'expect' => 'allow'],
                ['proto' => 'tcp', 'port' => 22, 'source' => '8.8.8.8', 'expect' => 'deny'],
            ]]],
        ];
        $this->state = $this->engine->initialState($this->scenario);
    });

    function fw(object $test, array $action): array
    {
        $result = $test->engine->apply($test->scenario, $test->state, $action);
        $test->state = $result['state'];

        return $result['output'];
    }

    it('takes the first matching rule, else the default policy', function () {
        $rules = [
            ['action' => 'allow', 'proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.0/24'],
            ['action' => 'deny', 'proto' => 'any', 'port' => null, 'source' => 'any'],
        ];
        expect($this->engine->evaluate($rules, 'allow', ['proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.200']))->toBe(['allow', 0])
            ->and($this->engine->evaluate($rules, 'allow', ['proto' => 'tcp', 'port' => 22, 'source' => '10.0.11.1']))->toBe(['deny', 1])
            ->and($this->engine->evaluate([], 'deny', ['proto' => 'udp', 'port' => 53, 'source' => '1.1.1.1']))->toBe(['deny', null])
            ->and($this->engine->evaluate([['action' => 'deny', 'proto' => 'tcp', 'port' => 80, 'source' => '0.0.0.0/0']], 'allow', ['proto' => 'tcp', 'port' => 80, 'source' => '9.9.9.9']))->toBe(['deny', 0]);
    });

    it('passes a traffic checkpoint once the rules give every packet its expected verdict', function () {
        $checkpoint = $this->scenario['checkpoints'][0];
        expect($this->engine->checkpointPassed($this->scenario, $this->state, $checkpoint))->toBeFalse();

        fw($this, ['type' => 'add_rule', 'position' => 0, 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.0/24']]);
        fw($this, ['type' => 'remove_rule', 'index' => 1]);
        fw($this, ['type' => 'set_default', 'policy' => 'deny']);
        expect($this->engine->checkpointPassed($this->scenario, $this->state, $checkpoint))->toBeTrue()
            ->and(fw($this, ['type' => 'test', 'packet' => ['proto' => 'tcp', 'port' => 22, 'source' => '8.8.8.8']]))->toBe(['verdict' => 'deny', 'rule' => null])
            ->and($this->engine->view($this->scenario, $this->state)['tests'])->toHaveCount(1);
    });

    it('moves rules', function () {
        fw($this, ['type' => 'add_rule', 'rule' => ['action' => 'deny', 'proto' => 'any', 'source' => 'any']]);
        fw($this, ['type' => 'move_rule', 'from' => 1, 'to' => 0]);
        expect($this->state['rules'][0])->toBe(['action' => 'deny', 'proto' => 'any', 'port' => null, 'source' => 'any']);
    });

    it('rejects malformed actions and too many rules', function (array $action, string $message) {
        expect(fn () => fw($this, $action))->toThrow(LabActionRejected::class, $message);
    })->with([
        [['type' => 'add_rule', 'rule' => ['action' => 'maybe', 'proto' => 'tcp', 'source' => 'any']], 'action must be allow or deny'],
        [['type' => 'add_rule', 'rule' => ['action' => 'allow', 'proto' => 'icmp', 'source' => 'any']], 'proto must be tcp, udp or any'],
        [['type' => 'add_rule', 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'port' => 70000, 'source' => 'any']], 'port must be 1 to 65535'],
        [['type' => 'add_rule', 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'source' => '10.0.0.0/33']], 'source must be any'],
        [['type' => 'remove_rule', 'index' => 5], 'There is no rule at that position.'],
        [['type' => 'test', 'packet' => ['proto' => 'tcp', 'port' => 22, 'source' => 'example.com']], 'a packet source is an IPv4 address'],
        [['type' => 'flush'], 'Unknown action'],
    ]);

    it('holds at most 20 rules', function () {
        for ($i = count($this->state['rules']); $i < FirewallRulesEngine::MAX_RULES; $i++) {
            fw($this, ['type' => 'add_rule', 'rule' => ['action' => 'deny', 'proto' => 'tcp', 'port' => 1000 + $i, 'source' => 'any']]);
        }
        expect(fn () => fw($this, ['type' => 'add_rule', 'rule' => ['action' => 'deny', 'proto' => 'tcp', 'source' => 'any']]))->toThrow(LabActionRejected::class, 'at most 20 rules');
    });

    it('never shows the hidden test packets', function () {
        expect(json_encode($this->engine->publicScenario($this->scenario)))->not->toContain('packets')->not->toContain('8.8.8.8');
    });
});

describe('http inspector', function () {
    beforeEach(function () {
        $this->engine = new HttpInspectorEngine;
        $this->scenario = [
            'app' => ['name' => 'Shop', 'origin' => 'https://shop.example'],
            'session' => ['name' => 'session', 'value' => 'mine'],
            'captured' => [],
            'routes' => [
                ['id' => 'own', 'method' => 'GET', 'path' => '/orders/1', 'cookie' => ['name' => 'session', 'value' => 'mine'], 'response' => ['status' => 200, 'headers' => [], 'body' => 'mine']],
                ['id' => 'theirs', 'method' => 'GET', 'path' => '/orders/2', 'cookie' => ['name' => 'session', 'value' => 'mine'], 'response' => ['status' => 200, 'headers' => [], 'body' => 'theirs']],
                ['id' => 'admin', 'method' => 'GET', 'path' => '/admin', 'response' => ['status' => 403, 'headers' => [], 'body' => 'no']],
            ],
            'checkpoints' => [['id' => 'idor', 'kind' => 'request', 'route' => 'theirs', 'title' => 'Fetch theirs']],
        ];
        $this->state = $this->engine->initialState($this->scenario);
    });

    function send(object $test, array $request): array
    {
        $result = $test->engine->apply($test->scenario, $test->state, ['type' => 'send', 'request' => $request]);
        $test->state = $result['state'];

        return $result['output']['response'];
    }

    it('answers from the scenario routes, checking the session cookie', function () {
        expect(send($this, ['method' => 'GET', 'path' => '/orders/2'])['status'])->toBe(401)
            ->and($this->engine->checkpointPassed($this->scenario, $this->state, $this->scenario['checkpoints'][0]))->toBeFalse()
            ->and(send($this, ['method' => 'GET', 'path' => '/orders/2', 'cookies' => ['session' => 'mine']])['body'])->toBe('theirs')
            ->and($this->engine->checkpointPassed($this->scenario, $this->state, $this->scenario['checkpoints'][0]))->toBeTrue()
            ->and(send($this, ['method' => 'GET', 'path' => '/admin'])['status'])->toBe(403)
            ->and(send($this, ['method' => 'DELETE', 'path' => '/orders/1'])['status'])->toBe(404)
            ->and($this->state['hits'])->toBe(['theirs'])
            ->and($this->engine->view($this->scenario, $this->state)['sent'])->toHaveCount(4);
    });

    it('rejects malformed requests', function (array $request, string $message) {
        expect(fn () => send($this, $request))->toThrow(LabActionRejected::class, $message);
    })->with([
        [['method' => 'TRACE', 'path' => '/'], 'The method must be one of'],
        [['method' => 'GET', 'path' => 'http://evil.example/'], 'The path starts with /'],
        [['method' => 'GET', 'path' => '/a b'], 'The path starts with /'],
        [['method' => 'GET', 'path' => '/', 'headers' => ['Bad Name' => 'x']], 'needs a name'],
        [['method' => 'GET', 'path' => '/', 'headers' => array_fill_keys(range('a', 'k'), 'x')], 'At most 10 headers'],
        [['method' => 'POST', 'path' => '/', 'body' => str_repeat('x', 2001)], 'at most 2000 characters'],
    ]);

    it('never shows the routes', function () {
        expect(json_encode($this->engine->publicScenario($this->scenario)))->not->toContain('routes')->not->toContain('theirs');
    });
});
