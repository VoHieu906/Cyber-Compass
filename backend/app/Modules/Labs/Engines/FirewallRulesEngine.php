<?php

namespace App\Modules\Labs\Engines;

/**
 * An inbound firewall in front of one server (ARCHITECTURE §7). The learner edits an ordered
 * rule list and a default policy; a packet takes the action of the first rule it matches, else
 * the default. Nothing leaves the server: packets are data.
 *
 * Scenario: server {name, ip}, default ('allow'|'deny'), rules (the starting list), checkpoints.
 * Checkpoint kind `traffic`: every listed packet {proto, port, source, expect} gets its expected
 * verdict. Those packets are hidden from the learner, who tests their own with `test`.
 */
final class FirewallRulesEngine implements LabEngine
{
    public const MAX_RULES = 20;

    private const PROTOS = ['tcp', 'udp', 'any'];

    private const TESTS_KEPT = 20;

    public function key(): string
    {
        return 'firewall-rules';
    }

    public function checkpointKinds(): array
    {
        return ['traffic'];
    }

    public function validateScenario(array $scenario): array
    {
        $errors = [];
        if (! is_string($scenario['server']['name'] ?? null) || ! $this->ip($scenario['server']['ip'] ?? null)) {
            $errors[] = 'server needs a name and an IPv4 address';
        }
        if (! in_array($scenario['default'] ?? null, ['allow', 'deny'], true)) {
            $errors[] = 'default must be allow or deny';
        }
        foreach ($scenario['rules'] ?? [] as $i => $rule) {
            if ($message = $this->ruleError($rule)) {
                $errors[] = "rule {$i}: {$message}";
            }
        }
        foreach ($scenario['checkpoints'] ?? [] as $i => $checkpoint) {
            if (($checkpoint['kind'] ?? null) !== 'traffic') {
                continue;
            }
            if (! is_array($checkpoint['packets'] ?? null) || $checkpoint['packets'] === []) {
                $errors[] = "checkpoint {$i}: packets are required";

                continue;
            }
            foreach ($checkpoint['packets'] as $j => $packet) {
                if ($message = $this->packetError($packet) ?? (in_array($packet['expect'] ?? null, ['allow', 'deny'], true) ? null : 'expect must be allow or deny')) {
                    $errors[] = "checkpoint {$i} packet {$j}: {$message}";
                }
            }
        }

        return $errors;
    }

    public function initialState(array $scenario): array
    {
        return ['rules' => array_values($scenario['rules'] ?? []), 'default' => $scenario['default'], 'tests' => []];
    }

    public function apply(array $scenario, array $state, array $action): array
    {
        $rules = $state['rules'];
        $output = ['message' => null];

        switch ($action['type'] ?? null) {
            case 'add_rule':
                $rule = $this->normalizeRule($action['rule'] ?? null);
                if (count($rules) >= self::MAX_RULES) {
                    throw new LabActionRejected('A rule list holds at most '.self::MAX_RULES.' rules.');
                }
                $at = $action['position'] ?? count($rules);
                if (! is_int($at) || $at < 0 || $at > count($rules)) {
                    throw new LabActionRejected('position must be between 0 and the number of rules.');
                }
                array_splice($rules, $at, 0, [$rule]);
                break;
            case 'remove_rule':
                $rules = $this->without($rules, $this->index($rules, $action['index'] ?? null));
                break;
            case 'move_rule':
                $from = $this->index($rules, $action['from'] ?? null);
                $to = $this->index($rules, $action['to'] ?? null);
                [$moved] = array_splice($rules, $from, 1);
                array_splice($rules, $to, 0, [$moved]);
                break;
            case 'set_default':
                if (! in_array($action['policy'] ?? null, ['allow', 'deny'], true)) {
                    throw new LabActionRejected('The default policy is allow or deny.');
                }
                $state['default'] = $action['policy'];
                break;
            case 'test':
                $packet = $action['packet'] ?? null;
                if ($message = $this->packetError($packet)) {
                    throw new LabActionRejected($message);
                }
                $packet = ['proto' => $packet['proto'], 'port' => $packet['port'], 'source' => $packet['source']];
                [$verdict, $matched] = $this->evaluate($rules, $state['default'], $packet);
                $output = ['verdict' => $verdict, 'rule' => $matched];
                $state['tests'] = array_slice([...$state['tests'], $packet + ['verdict' => $verdict, 'rule' => $matched]], -self::TESTS_KEPT);
                break;
            default:
                throw new LabActionRejected('Unknown action. Use add_rule, remove_rule, move_rule, set_default or test.');
        }
        $state['rules'] = array_values($rules);

        return ['state' => $state, 'output' => $output];
    }

    public function checkpointPassed(array $scenario, array $state, array $checkpoint): bool
    {
        foreach ($checkpoint['packets'] as $packet) {
            if ($this->evaluate($state['rules'], $state['default'], $packet)[0] !== $packet['expect']) {
                return false;
            }
        }

        return true;
    }

    public function publicScenario(array $scenario): array
    {
        return ['server' => $scenario['server'], 'max_rules' => self::MAX_RULES];
    }

    public function view(array $scenario, array $state): array
    {
        return ['rules' => $state['rules'], 'default' => $state['default'], 'tests' => $state['tests']];
    }

    /**
     * First matching rule wins; otherwise the default policy.
     *
     * @return array{0: string, 1: ?int} verdict, and the index of the rule that decided it
     */
    public function evaluate(array $rules, string $default, array $packet): array
    {
        foreach (array_values($rules) as $i => $rule) {
            $proto = $rule['proto'] === 'any' || $rule['proto'] === $packet['proto'];
            $port = $rule['port'] === null || $rule['port'] === $packet['port'];
            if ($proto && $port && $this->inSource($packet['source'], $rule['source'])) {
                return [$rule['action'], $i];
            }
        }

        return [$default, null];
    }

    private function inSource(string $ip, string $source): bool
    {
        if ($source === 'any') {
            return true;
        }
        [$network, $bits] = str_contains($source, '/') ? explode('/', $source) : [$source, '32'];
        $mask = (int) $bits === 0 ? 0 : (~0 << (32 - (int) $bits)) & 0xFFFFFFFF;

        return (ip2long($ip) & $mask) === (ip2long($network) & $mask);
    }

    /** @return array{action: string, proto: string, port: ?int, source: string} */
    private function normalizeRule(mixed $rule): array
    {
        if ($message = $this->ruleError($rule)) {
            throw new LabActionRejected($message);
        }

        return ['action' => $rule['action'], 'proto' => $rule['proto'], 'port' => $rule['port'] ?? null, 'source' => $rule['source']];
    }

    private function ruleError(mixed $rule): ?string
    {
        return match (true) {
            ! is_array($rule) => 'a rule needs action, proto, port and source',
            ! in_array($rule['action'] ?? null, ['allow', 'deny'], true) => 'action must be allow or deny',
            ! in_array($rule['proto'] ?? null, self::PROTOS, true) => 'proto must be tcp, udp or any',
            isset($rule['port']) && (! is_int($rule['port']) || $rule['port'] < 1 || $rule['port'] > 65535) => 'port must be 1 to 65535, or empty for any port',
            ! is_string($rule['source'] ?? null) || ($rule['source'] !== 'any' && ! $this->cidr($rule['source'])) => 'source must be any, an IPv4 address or a CIDR range such as 10.0.0.0/24',
            default => null,
        };
    }

    private function packetError(mixed $packet): ?string
    {
        return match (true) {
            ! is_array($packet) => 'a test packet needs proto, port and source',
            ! in_array($packet['proto'] ?? null, ['tcp', 'udp'], true) => 'a packet is tcp or udp',
            ! is_int($packet['port'] ?? null) || $packet['port'] < 1 || $packet['port'] > 65535 => 'a packet port is 1 to 65535',
            ! $this->ip($packet['source'] ?? null) => 'a packet source is an IPv4 address',
            default => null,
        };
    }

    private function cidr(string $value): bool
    {
        if (! str_contains($value, '/')) {
            return $this->ip($value);
        }
        [$ip, $bits] = explode('/', $value, 2);

        return $this->ip($ip) && ctype_digit($bits) && (int) $bits <= 32;
    }

    private function ip(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    private function index(array $rules, mixed $index): int
    {
        if (! is_int($index) || ! array_key_exists($index, $rules)) {
            throw new LabActionRejected('There is no rule at that position.');
        }

        return $index;
    }

    private function without(array $rules, int $index): array
    {
        unset($rules[$index]);

        return array_values($rules);
    }
}
