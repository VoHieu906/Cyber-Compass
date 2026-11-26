<?php

namespace App\Modules\Labs\Engines;

/**
 * Review evidence and mark what matters: a suspicious email (raw headers, what the recipient
 * saw, a server log), or code with numbered lines (D80, ARCHITECTURE §7). The learner reads
 * artifacts, each with one or more views of plain text lines, and marks the lines that show a
 * problem. Nothing is executed, fetched or interpreted: every line is text, and the browser shows
 * it as text (§7.1).
 *
 * Scenario: artifacts [{id, title, views [{id, label, lines, mono?}], max_extra?}], indicators
 * [{id, title, explanation, lines [{artifact, view, line}]}] (an indicator is found when any of its
 * lines is marked), checkpoints. Checkpoint kind `findings`: every listed indicator is found and
 * no artifact holds more marks than its cap, which is the number of lines the indicators point to
 * in it plus `max_extra` (default 2), so marking everything cannot pass. The cap is public; the
 * indicators and their explanations are not, until the lab is complete.
 */
final class EvidenceReviewEngine implements LabEngine
{
    public const DEFAULT_EXTRA = 2;

    private const MAX_LINES = 600;

    public function key(): string
    {
        return 'evidence-review';
    }

    public function checkpointKinds(): array
    {
        return ['findings'];
    }

    public function validateScenario(array $scenario): array
    {
        $errors = [];
        $lines = [];
        $total = 0;
        $artifactIds = [];
        foreach ($scenario['artifacts'] as $i => $artifact) {
            if (isset($artifactIds[$artifact['id']])) {
                $errors["artifacts.{$i}.id"][] = 'duplicate artifact id';
            }
            $artifactIds[$artifact['id']] = true;
            $views = [];
            foreach ($artifact['views'] as $j => $view) {
                if (isset($views[$view['id']])) {
                    $errors["artifacts.{$i}.views.{$j}.id"][] = 'duplicate view id in this artifact';
                }
                $views[$view['id']] = true;
                $lines[$artifact['id']][$view['id']] = count($view['lines']);
                $total += count($view['lines']);
            }
        }
        if ($total > self::MAX_LINES) {
            $errors['artifacts'][] = 'the artifacts may hold at most '.self::MAX_LINES.' lines in total';
        }

        $indicators = [];
        foreach ($scenario['indicators'] as $i => $indicator) {
            if (isset($indicators[$indicator['id']])) {
                $errors["indicators.{$i}.id"][] = 'duplicate indicator id';
            }
            $indicators[$indicator['id']] = true;
            foreach ($indicator['lines'] as $j => $ref) {
                $count = $lines[$ref['artifact']][$ref['view']] ?? null;
                if ($count === null) {
                    $errors["indicators.{$i}.lines.{$j}"][] = 'artifact and view must be ones of the scenario';
                } elseif ($ref['line'] > $count) {
                    $errors["indicators.{$i}.lines.{$j}.line"][] = "that view has only {$count} lines";
                }
            }
        }
        foreach ($scenario['checkpoints'] as $i => $checkpoint) {
            foreach ($checkpoint['kind'] === 'findings' ? $checkpoint['indicators'] : [] as $j => $id) {
                if (! isset($indicators[$id])) {
                    $errors["checkpoints.{$i}.indicators.{$j}"][] = 'indicator must be one of the indicator ids';
                }
            }
        }

        return $errors;
    }

    public function initialState(array $scenario): array
    {
        return ['marks' => []];
    }

    public function apply(array $scenario, array $state, array $action): array
    {
        if (($action['type'] ?? null) !== 'mark') {
            throw new LabActionRejected('Unknown action. Use mark.');
        }
        $artifact = $action['artifact'] ?? null;
        $view = $action['view'] ?? null;
        $line = $action['line'] ?? null;
        $on = $action['on'] ?? null;
        $count = null;
        foreach ($scenario['artifacts'] as $a) {
            foreach ($a['views'] as $v) {
                if ($a['id'] === $artifact && $v['id'] === $view) {
                    $count = count($v['lines']);
                }
            }
        }
        if ($count === null || ! is_int($line) || $line < 1 || $line > $count || ! is_bool($on)) {
            throw new LabActionRejected('Mark a line of one of the artifacts, with on set to true or false.');
        }
        $key = $this->lineKey($artifact, $view, $line);
        $marks = array_values(array_diff($state['marks'], [$key]));
        if ($on) {
            $marks[] = $key;
        }
        $state['marks'] = $marks;

        return ['state' => $state, 'output' => ['marked' => $on, 'count' => count($marks)]];
    }

    public function checkpointPassed(array $scenario, array $state, array $checkpoint): bool
    {
        $marks = array_flip($state['marks']);
        $indicators = array_column($scenario['indicators'], null, 'id');
        foreach ($checkpoint['indicators'] as $id) {
            if (! $this->found($indicators[$id], $marks)) {
                return false;
            }
        }

        return $this->over($scenario, $state['marks']) === [];
    }

    public function citableIds(array $scenario): array
    {
        $ids = [];
        foreach ($scenario['artifacts'] as $a) {
            foreach ($a['views'] as $v) {
                foreach (array_keys($v['lines']) as $n) {
                    $ids[] = $this->lineKey($a['id'], $v['id'], $n + 1);
                }
            }
        }

        return $ids;
    }

    /** What the learner has marked, in reading order. */
    public function citable(array $scenario, array $state): array
    {
        $marks = array_flip($state['marks']);
        $out = [];
        foreach ($scenario['artifacts'] as $a) {
            foreach ($a['views'] as $v) {
                foreach ($v['lines'] as $n => $text) {
                    $key = $this->lineKey($a['id'], $v['id'], $n + 1);
                    if (isset($marks[$key])) {
                        $out[] = ['id' => $key, 'label' => "{$a['title']}, {$v['label']}, line ".($n + 1).': '.mb_strimwidth($text, 0, 80, '…')];
                    }
                }
            }
        }

        return $out;
    }

    public function publicScenario(array $scenario): array
    {
        $evidence = $this->evidenceCounts($scenario);

        return ['artifacts' => array_map(fn (array $a) => [
            'id' => $a['id'],
            'title' => $a['title'],
            'views' => array_map(fn (array $v) => ['id' => $v['id'], 'label' => $v['label'], 'lines' => $v['lines'], 'mono' => $v['mono'] ?? true], $a['views']),
            'max_marks' => $this->cap($a, $evidence),
        ], $scenario['artifacts'])];
    }

    public function view(array $scenario, array $state): array
    {
        return ['marks' => $state['marks']];
    }

    /** @return array{indicators: list<array<string, mixed>>, wrong: list<array<string, mixed>>} */
    public function debrief(array $scenario, array $state): ?array
    {
        $marks = array_flip($state['marks']);
        $required = [];
        foreach ($scenario['checkpoints'] as $c) {
            foreach ($c['kind'] === 'findings' ? $c['indicators'] : [] as $id) {
                $required[$id] = true;
            }
        }
        $evidence = [];
        $indicators = [];
        foreach ($scenario['indicators'] as $indicator) {
            foreach ($indicator['lines'] as $ref) {
                $evidence[$this->lineKey($ref['artifact'], $ref['view'], $ref['line'])] = true;
            }
            $indicators[] = [
                'id' => $indicator['id'],
                'title' => $indicator['title'],
                'explanation' => $indicator['explanation'],
                'required' => isset($required[$indicator['id']]),
                'found' => $this->found($indicator, $marks),
                'lines' => array_map(fn (array $r) => $this->describe($scenario, $r['artifact'], $r['view'], $r['line']), $indicator['lines']),
            ];
        }
        $wrong = [];
        foreach ($state['marks'] as $key) {
            if (! isset($evidence[$key])) {
                [$a, $v, $n] = explode(':', $key);
                $wrong[] = $this->describe($scenario, $a, $v, (int) $n);
            }
        }

        return ['indicators' => $indicators, 'wrong' => $wrong];
    }

    /**
     * @param  array<string, mixed>  $indicator
     * @param  array<string, int>  $marks  flipped: key => index
     */
    private function found(array $indicator, array $marks): bool
    {
        foreach ($indicator['lines'] as $ref) {
            if (isset($marks[$this->lineKey($ref['artifact'], $ref['view'], $ref['line'])])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, int> how many lines the indicators point to, by artifact
     */
    private function evidenceCounts(array $scenario): array
    {
        $seen = [];
        foreach ($scenario['indicators'] as $indicator) {
            foreach ($indicator['lines'] as $ref) {
                $seen[$this->lineKey($ref['artifact'], $ref['view'], $ref['line'])] = $ref['artifact'];
            }
        }

        return array_count_values($seen);
    }

    /**
     * @param  array<string, mixed>  $artifact
     * @param  array<string, int>  $evidence
     */
    private function cap(array $artifact, array $evidence): int
    {
        return ($evidence[$artifact['id']] ?? 0) + ($artifact['max_extra'] ?? self::DEFAULT_EXTRA);
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  list<string>  $marks
     * @return list<string> artifacts with more marks than their cap
     */
    private function over(array $scenario, array $marks): array
    {
        $evidence = $this->evidenceCounts($scenario);
        $over = [];
        foreach ($scenario['artifacts'] as $a) {
            $made = count(array_filter($marks, fn (string $k) => str_starts_with($k, $a['id'].':')));
            if ($made > $this->cap($a, $evidence)) {
                $over[] = $a['id'];
            }
        }

        return $over;
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    private function describe(array $scenario, string $artifact, string $view, int $line): array
    {
        foreach ($scenario['artifacts'] as $a) {
            foreach ($a['views'] as $v) {
                if ($a['id'] === $artifact && $v['id'] === $view) {
                    return ['artifact' => $a['title'], 'view' => $v['label'], 'line' => $line, 'text' => $v['lines'][$line - 1] ?? ''];
                }
            }
        }

        return ['artifact' => $artifact, 'view' => $view, 'line' => $line, 'text' => ''];
    }

    private function lineKey(string $artifact, string $view, int $line): string
    {
        return "{$artifact}:{$view}:{$line}";
    }
}
