<?php

namespace App\Modules\Labs\Engines;

/**
 * The state graph of an incident-response scenario (D80): every state reachable from the start,
 * and for each one which outcome checkpoints can still pass. A state is a node and the values of
 * the scenario's variables. The walk is bounded (MAX_STATES) and pure, so the validator and the
 * engine read the same table, and the engine never searches while a learner plays: it looks the
 * state up. The table stays on the server.
 */
final class IncidentGraph
{
    public const MAX_STATES = 500;

    /** @var array<string, array<string, mixed>> built tables by scenario fingerprint, once per scenario per process */
    private static array $built = [];

    /**
     * @param  array<string, mixed>  $scenario  already checked for references
     * @return array{states: array<string, array{node: string, vars: array<string, string>, succ: array<string, string>, ends: bool, complete: bool, reach: list<string>}>, start: string, truncated: bool}
     */
    public static function of(array $scenario): array
    {
        $key = sha1((string) json_encode([$scenario['variables'], $scenario['start'], $scenario['nodes'], self::outcomes($scenario)]));
        if (! isset(self::$built[$key])) {
            if (count(self::$built) >= 8) {
                array_shift(self::$built);
            }
            self::$built[$key] = self::build($scenario);
        }

        return self::$built[$key];
    }

    /** @param  array<string, string>  $vars  in any order: a stored state comes back from the database with its keys reordered */
    public static function key(string $node, array $vars): string
    {
        ksort($vars);

        return $node.'|'.json_encode($vars);
    }

    /**
     * @param  array<string, mixed>  $choice
     * @param  array<string, string>  $vars
     */
    public static function available(array $choice, array $vars): bool
    {
        $any = $choice['requires'] ?? [];
        if ($any === []) {
            return true;
        }
        foreach ($any as $all) {
            if (self::matches($all, $vars)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $conditions
     * @param  array<string, string>  $vars
     */
    public static function matches(array $conditions, array $vars): bool
    {
        foreach ($conditions as $var => $value) {
            if (($vars[$var] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $checkpoint  of kind `outcome`
     * @param  array<string, string>  $vars
     */
    public static function holds(array $checkpoint, bool $ending, array $vars): bool
    {
        return self::matches($checkpoint['when'], $vars) && (! ($checkpoint['ending'] ?? false) || $ending);
    }

    /** @return array<string, array<string, mixed>> the outcome checkpoints by id */
    public static function outcomes(array $scenario): array
    {
        $out = [];
        foreach ($scenario['checkpoints'] as $c) {
            if ($c['kind'] === 'outcome') {
                $out[$c['id']] = $c;
            }
        }

        return $out;
    }

    /** @return array<string, string> the variables at the start, in the order they are defined */
    public static function initialVars(array $scenario): array
    {
        $vars = [];
        foreach ($scenario['variables'] as $v) {
            $vars[$v['id']] = $v['initial'];
        }

        return $vars;
    }

    /** @return array<string, mixed> */
    private static function build(array $scenario): array
    {
        $nodes = array_column($scenario['nodes'], null, 'id');
        $outcomes = self::outcomes($scenario);
        $start = self::key($scenario['start'], self::initialVars($scenario));
        $states = [$start => ['node' => $scenario['start'], 'vars' => self::initialVars($scenario), 'succ' => [], 'ends' => false, 'complete' => false, 'reach' => []]];
        $queue = [$start];
        $truncated = false;
        for ($i = 0; $i < count($queue); $i++) {
            $key = $queue[$i];
            $node = $nodes[$states[$key]['node']];
            if ($node['ending'] ?? false) {
                continue;
            }
            foreach ($node['choices'] ?? [] as $choice) {
                if (! self::available($choice, $states[$key]['vars'])) {
                    continue;
                }
                $vars = array_merge($states[$key]['vars'], $choice['effects'] ?? []);
                $next = self::key($choice['next'], $vars);
                if (! isset($states[$next])) {
                    if (count($states) >= self::MAX_STATES) {
                        $truncated = true;

                        continue;
                    }
                    $states[$next] = ['node' => $choice['next'], 'vars' => $vars, 'succ' => [], 'ends' => false, 'complete' => false, 'reach' => []];
                    $queue[] = $next;
                }
                $states[$key]['succ'][$choice['id']] = $next;
            }
        }

        // Fixpoints over the graph: a state ends, completes or reaches a checkpoint if it does itself or any successor does.
        $holding = [];
        foreach ($states as $key => $state) {
            $ending = (bool) ($nodes[$state['node']]['ending'] ?? false);
            $states[$key]['ends'] = $ending;
            $all = true;
            foreach ($outcomes as $id => $c) {
                $holding[$key][$id] = self::holds($c, $ending, $state['vars']);
                $all = $all && $holding[$key][$id];
            }
            $states[$key]['complete'] = $all;
            $states[$key]['reach'] = array_keys(array_filter($holding[$key] ?? []));
        }
        do {
            $changed = false;
            foreach (array_reverse(array_keys($states)) as $key) {
                foreach ($states[$key]['succ'] as $next) {
                    foreach (['ends', 'complete'] as $flag) {
                        if (! $states[$key][$flag] && $states[$next][$flag]) {
                            $states[$key][$flag] = true;
                            $changed = true;
                        }
                    }
                    $merged = array_values(array_unique([...$states[$key]['reach'], ...$states[$next]['reach']]));
                    if (count($merged) !== count($states[$key]['reach'])) {
                        $states[$key]['reach'] = $merged;
                        $changed = true;
                    }
                }
            }
        } while ($changed);

        return ['states' => $states, 'start' => $start, 'truncated' => $truncated];
    }
}
