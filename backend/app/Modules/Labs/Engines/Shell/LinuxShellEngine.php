<?php

namespace App\Modules\Labs\Engines\Shell;

use App\Modules\Labs\Engines\LabActionRejected;
use App\Modules\Labs\Engines\LabEngine;
use App\Modules\Labs\Engines\LabLimits;
use App\Modules\Labs\Engines\SafeRegex;

/**
 * A read-only Linux shell over a virtual file system (ARCHITECTURE §7, §7.1). Files are a map
 * of absolute path => text in the scenario; nothing touches the real file system and no process
 * runs. Supported: pwd, ls, cd, cat, head, tail, grep, wc, sort, uniq, cut, find, echo, whoami,
 * id, hostname, help, clear, with up to five `|` stages.
 *
 * Scenario: hostname, user, home, cwd, files {path: text}, dirs (optional extra directories),
 * checkpoints. Checkpoint kind `read`: the learner has read {path} with cat, head, tail, grep or wc.
 */
final class LinuxShellEngine implements LabEngine
{
    /** Tools people reach for that this lab does not offer, with a reason instead of "not found". */
    private const UNAVAILABLE = ['rm', 'mv', 'cp', 'touch', 'mkdir', 'chmod', 'chown', 'sudo', 'su', 'nano', 'vi', 'vim', 'curl', 'wget', 'ssh', 'python', 'python3', 'bash', 'sh', 'kill', 'awk', 'sed', 'less', 'more'];

    private const COMMANDS = ['pwd', 'ls', 'cd', 'cat', 'head', 'tail', 'grep', 'wc', 'sort', 'uniq', 'cut', 'find', 'echo', 'whoami', 'id', 'hostname', 'help', 'clear'];

    private const HISTORY = 40;

    private const HISTORY_OUTPUT_BYTES = 4096;

    private const MAX_FILES_BYTES = 128 * 1024;

    public function __construct(private readonly ShellParser $parser) {}

    public function key(): string
    {
        return 'linux-shell';
    }

    public function checkpointKinds(): array
    {
        return ['read'];
    }

    public function validateScenario(array $scenario): array
    {
        $errors = [];
        $files = $scenario['files'];
        $size = 0;
        foreach ($files as $path => $text) {
            if ($this->normalize((string) $path, '/') !== $path || $path === '/') {
                $errors['files'][] = 'file path '.json_encode($path).' must be absolute and normalized';
            }
            $size += strlen($text);
        }
        if ($size > self::MAX_FILES_BYTES) {
            $errors['files'][] = 'files may hold at most '.(self::MAX_FILES_BYTES / 1024).' KB in total';
        }
        $dirs = $this->dirs($scenario);
        foreach (['home', 'cwd'] as $field) {
            if (! isset($dirs[$scenario[$field]])) {
                $errors[$field][] = "{$field} must be a directory of the scenario";
            }
        }
        foreach ($scenario['checkpoints'] as $i => $checkpoint) {
            if ($checkpoint['kind'] === 'read' && ! isset($files[$checkpoint['path']])) {
                $errors["checkpoints.{$i}.path"][] = 'path must be one of the files';
            }
        }

        return $errors;
    }

    public function initialState(array $scenario): array
    {
        return ['cwd' => $scenario['cwd'], 'read' => [], 'history' => []];
    }

    public function apply(array $scenario, array $state, array $action): array
    {
        if (($action['type'] ?? null) !== 'command' || ! is_string($action['command'] ?? null)) {
            throw new LabActionRejected('Send a command.');
        }
        $command = trim($action['command']);
        $parsed = $this->parser->parse($command);

        if (isset($parsed['error'])) {
            $output = ['text' => $parsed['error'], 'error' => true];
        } elseif ($parsed['stages'] === []) {
            $output = ['text' => '', 'error' => false];
        } elseif ($parsed['stages'][0][0] === 'clear' && count($parsed['stages']) === 1) {
            $state['history'] = [];

            return ['state' => $state, 'output' => ['text' => '', 'error' => false, 'clear' => true]];
        } else {
            [$text, $error] = $this->pipeline($scenario, $state, $parsed['stages']);
            $output = ['text' => LabLimits::capOutput($text, '[output truncated at 64 KB]'), 'error' => $error];
        }

        $state['history'][] = ['command' => mb_substr($command, 0, LabLimits::MAX_COMMAND_LENGTH), 'output' => mb_strcut($output['text'], 0, self::HISTORY_OUTPUT_BYTES), 'error' => $output['error']];
        $state['history'] = array_slice($state['history'], -self::HISTORY);

        return ['state' => $state, 'output' => $output];
    }

    public function checkpointPassed(array $scenario, array $state, array $checkpoint): bool
    {
        return $checkpoint['kind'] === 'read' && in_array($checkpoint['path'], $state['read'] ?? [], true);
    }

    public function publicScenario(array $scenario): array
    {
        return ['hostname' => $scenario['hostname'], 'user' => $scenario['user'], 'commands' => self::COMMANDS];
    }

    public function view(array $scenario, array $state): array
    {
        return ['cwd' => $state['cwd'], 'prompt' => $this->prompt($scenario, $state['cwd']), 'history' => $state['history']];
    }

    /**
     * Runs the stages, feeding each one's output to the next. The first error stops the pipeline.
     *
     * @param  list<list<string>>  $stages
     * @return array{0: string, 1: bool} output text, and whether it is an error
     */
    private function pipeline(array $scenario, array &$state, array $stages): array
    {
        $stdin = null;
        foreach ($stages as $argv) {
            $name = array_shift($argv);
            $result = match (true) {
                in_array($name, self::UNAVAILABLE, true) => [null, "{$name}: not available in this lab. The system is read-only here; type help for the commands you can use."],
                ! in_array($name, self::COMMANDS, true) => [null, "{$name}: command not found. Type help for the commands this lab supports."],
                default => $this->run($name, $argv, $stdin, $scenario, $state),
            };
            [$out, $error] = $result;
            if ($error !== null) {
                return [$error, true];
            }
            $stdin = $out;
        }

        return [rtrim((string) $stdin, "\n"), false];
    }

    /**
     * @param  list<string>  $args
     * @return array{0: ?string, 1: ?string} output, or an error message
     */
    private function run(string $name, array $args, ?string $stdin, array $scenario, array &$state): array
    {
        $files = $scenario['files'];
        $cwd = $state['cwd'];
        $home = $scenario['home'];
        $path = fn (string $p) => $this->normalize($p, $cwd, $home);
        // Reads a file argument, recording it for `read` checkpoints.
        $read = function (string $arg) use ($scenario, $files, $path, &$state, $name): array {
            $p = $path($arg);
            if (isset($files[$p])) {
                $state['read'] = array_values(array_unique([...$state['read'], $p]));

                return [$files[$p], null];
            }

            return [null, isset($this->dirs($scenario)[$p]) ? "{$name}: {$arg}: Is a directory" : "{$name}: {$arg}: No such file or directory"];
        };
        // The text a filter works on: the named files, else the previous stage's output.
        $input = function (array $operands) use ($stdin, $read, $name): array {
            if ($operands === []) {
                return $stdin === null ? [null, "{$name}: give a file, or pipe output into it"] : [$stdin, null];
            }
            $text = '';
            foreach ($operands as $operand) {
                [$content, $error] = $read($operand);
                if ($error !== null) {
                    return [null, $error];
                }
                $text .= rtrim($content, "\n")."\n";
            }

            return [$text, null];
        };

        switch ($name) {
            case 'pwd':
                return [$cwd."\n", null];
            case 'whoami':
                return [$scenario['user']."\n", null];
            case 'hostname':
                return [$scenario['hostname']."\n", null];
            case 'id':
                return ["uid=1000({$scenario['user']}) gid=1000({$scenario['user']}) groups=1000({$scenario['user']})\n", null];
            case 'echo':
                return [implode(' ', $args)."\n", null];
            case 'help':
                return ['Commands: '.implode(', ', self::COMMANDS).".\nPipes work: grep 'Failed' /var/log/auth.log | wc -l\nThe system is read-only; nothing you type changes it.\n", null];
            case 'cd':
                $target = $path($args[0] ?? $home);
                if (! isset($this->dirs($scenario)[$target])) {
                    return [null, 'cd: '.($args[0] ?? $home).': No such directory'];
                }
                $state['cwd'] = $target;

                return ['', null];
            case 'ls':
                return $this->ls($scenario, $args, $path);
            case 'cat':
                return $input($args);
            case 'head':
            case 'tail':
                [$count, $operands, $error] = $this->lineCount($name, $args);
                if ($error) {
                    return [null, $error];
                }
                [$text, $error] = $input($operands);
                if ($error) {
                    return [null, $error];
                }
                $lines = $this->lines($text);

                return [$this->join($name === 'head' ? array_slice($lines, 0, $count) : array_slice($lines, -$count ?: count($lines))), null];
            case 'grep':
                return $this->grep($args, $input, count(array_filter($args, fn ($a) => ! str_starts_with($a, '-'))) > 2);
            case 'wc':
                [$flags, $operands] = $this->flags($args, 'lwc');
                if (is_string($flags)) {
                    return [null, "wc: {$flags}"];
                }
                [$text, $error] = $input($operands);
                if ($error) {
                    return [null, $error];
                }
                $counts = ['l' => substr_count($text, "\n"), 'w' => count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY)), 'c' => strlen($text)];
                $shown = $flags === [] ? $counts : array_intersect_key($counts, array_flip($flags));

                return [implode(' ', $shown).($operands ? ' '.implode(' ', $operands) : '')."\n", null];
            case 'sort':
                [$flags, $operands] = $this->flags($args, 'rnu');
                if (is_string($flags)) {
                    return [null, "sort: {$flags}"];
                }
                [$text, $error] = $input($operands);
                if ($error) {
                    return [null, $error];
                }
                $lines = $this->lines($text);
                in_array('n', $flags, true) ? usort($lines, fn ($a, $b) => (float) $a <=> (float) $b ?: strcmp($a, $b)) : sort($lines, SORT_STRING);
                if (in_array('r', $flags, true)) {
                    $lines = array_reverse($lines);
                }
                if (in_array('u', $flags, true)) {
                    $lines = array_values(array_unique($lines));
                }

                return [$this->join($lines), null];
            case 'uniq':
                [$flags, $operands] = $this->flags($args, 'c');
                if (is_string($flags)) {
                    return [null, "uniq: {$flags}"];
                }
                [$text, $error] = $input($operands);
                if ($error) {
                    return [null, $error];
                }
                $runs = [];
                foreach ($this->lines($text) as $line) {
                    if ($runs !== [] && $runs[array_key_last($runs)][0] === $line) {
                        $runs[array_key_last($runs)][1]++;
                    } else {
                        $runs[] = [$line, 1];
                    }
                }

                return [$this->join(array_map(fn ($r) => $flags ? str_pad((string) $r[1], 7, ' ', STR_PAD_LEFT).' '.$r[0] : $r[0], $runs)), null];
            case 'cut':
                return $this->cut($args, $input);
            case 'find':
                return $this->find($scenario, $args, $path, $cwd);
        }

        return [null, "{$name}: command not found"];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function grep(array $args, callable $input, bool $manyFiles): array
    {
        $flags = [];
        $operands = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '-') && $operands === [] && $arg !== '-') {
                foreach (str_split(substr($arg, 1)) as $f) {
                    if (! in_array($f, ['i', 'n', 'c', 'v', 'E'], true)) {
                        return [null, "grep: unsupported option -{$f} (supported: -i -n -c -v -E)"];
                    }
                    $flags[$f] = true;
                }
            } else {
                $operands[] = $arg;
            }
        }
        $pattern = array_shift($operands);
        if ($pattern === null) {
            return [null, 'grep: give a pattern, for example grep "Failed password" /var/log/auth.log'];
        }

        // Literal matching by default; a regular expression only with -E, through SafeRegex (§7.1).
        $regex = null;
        if (isset($flags['E'])) {
            $regex = SafeRegex::compile($pattern, isset($flags['i']));
            if (is_string($regex)) {
                return [null, "grep: {$regex}"];
            }
        } elseif (mb_strlen($pattern) > LabLimits::MAX_PATTERN_LENGTH) {
            return [null, 'grep: pattern too long (at most '.LabLimits::MAX_PATTERN_LENGTH.' characters)'];
        }

        $out = [];
        $sources = $operands === [] ? [null] : $operands;
        foreach ($sources as $source) {
            [$text, $error] = $input($source === null ? [] : [$source]);
            if ($error) {
                return [null, $error];
            }
            $lines = $this->lines($text);
            try {
                $keys = $regex
                    ? $regex->matchingKeys($lines, isset($flags['v']))
                    : array_keys(array_filter($lines, fn ($l) => (isset($flags['i']) ? mb_stripos($l, $pattern) !== false : str_contains($l, $pattern)) !== isset($flags['v'])));
            } catch (LabActionRejected $e) {
                return [null, 'grep: '.$e->getMessage()];
            }
            $prefix = $manyFiles && $source !== null ? "{$source}:" : '';
            if (isset($flags['c'])) {
                $out[] = $prefix.count($keys);

                continue;
            }
            foreach ($keys as $k) {
                $out[] = $prefix.(isset($flags['n']) ? ($k + 1).':' : '').$lines[$k];
            }
        }

        return [$this->join($out), null];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function cut(array $args, callable $input): array
    {
        $delimiter = "\t";
        $fields = null;
        $operands = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if ($arg === '-d' || $arg === '-f') {
                $value = $args[++$i] ?? null;
            } elseif (str_starts_with($arg, '-d') || str_starts_with($arg, '-f')) {
                $value = substr($arg, 2);
                $arg = substr($arg, 0, 2);
            } else {
                $operands[] = $arg;

                continue;
            }
            if ($value === null || $value === '') {
                return [null, "cut: {$arg} needs a value"];
            }
            if ($arg === '-d') {
                if (mb_strlen($value) !== 1) {
                    return [null, 'cut: the delimiter must be a single character'];
                }
                $delimiter = $value;
            } else {
                $fields = [];
                foreach (explode(',', $value) as $f) {
                    if (! ctype_digit($f) || (int) $f < 1 || (int) $f > 100) {
                        return [null, 'cut: fields are numbers from 1, such as -f 1,3'];
                    }
                    $fields[] = (int) $f;
                }
            }
        }
        if ($fields === null) {
            return [null, 'cut: give the fields with -f, for example cut -d " " -f 1'];
        }
        [$text, $error] = $input($operands);
        if ($error) {
            return [null, $error];
        }

        return [$this->join(array_map(function ($line) use ($delimiter, $fields) {
            $parts = explode($delimiter, $line);

            return implode($delimiter, array_map(fn ($f) => $parts[$f - 1] ?? '', $fields));
        }, $this->lines($text))), null];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function ls(array $scenario, array $args, callable $path): array
    {
        $long = false;
        $all = false;
        $targets = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '-') && strlen($arg) > 1) {
                foreach (str_split(substr($arg, 1)) as $f) {
                    match ($f) {
                        'l' => $long = true,
                        'a' => $all = true,
                        default => null,
                    };
                    if (! in_array($f, ['l', 'a'], true)) {
                        return [null, "ls: unsupported option -{$f} (supported: -l -a)"];
                    }
                }
            } else {
                $targets[] = $arg;
            }
        }
        $target = $path($targets[0] ?? '.');
        $files = $scenario['files'];
        $dirs = $this->dirs($scenario);
        if (isset($files[$target])) {
            return [($long ? $this->longLine(basename($target), strlen($files[$target]), false) : basename($target))."\n", null];
        }
        if (! isset($dirs[$target])) {
            return [null, 'ls: cannot access '.($targets[0] ?? '.').': No such file or directory'];
        }

        $entries = [];
        $prefix = rtrim($target, '/').'/';
        foreach ([...array_keys($dirs), ...array_keys($files)] as $p) {
            if ($p !== $target && str_starts_with($p, $prefix) && ! str_contains(substr($p, strlen($prefix)), '/')) {
                $entries[substr($p, strlen($prefix))] = isset($dirs[$p]);
            }
        }
        ksort($entries, SORT_STRING);
        $lines = [];
        foreach ($entries as $name => $isDir) {
            if (! $all && str_starts_with($name, '.')) {
                continue;
            }
            $lines[] = $long ? $this->longLine($name, $isDir ? 4096 : strlen($files[$prefix.$name]), $isDir) : $name.($isDir ? '/' : '');
        }

        return [$this->join($lines), null];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function find(array $scenario, array $args, callable $path, string $cwd): array
    {
        $start = null;
        $name = null;
        $type = null;
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if ($arg === '-name' || $arg === '-type') {
                $value = $args[++$i] ?? null;
                if ($value === null) {
                    return [null, "find: {$arg} needs a value"];
                }
                if ($arg === '-type' && ! in_array($value, ['f', 'd'], true)) {
                    return [null, 'find: -type takes f (files) or d (directories)'];
                }
                $arg === '-name' ? $name = $value : $type = $value;
            } elseif (str_starts_with($arg, '-')) {
                // -exec, -delete, -ok and every other action are refused (§7.1).
                return [null, "find: {$arg} is not supported in this lab (supported: -name, -type)"];
            } elseif ($start === null) {
                $start = $arg;
            } else {
                return [null, 'find: give one starting directory'];
            }
        }
        $root = $path($start ?? '.');
        $dirs = $this->dirs($scenario);
        if (! isset($dirs[$root]) && ! isset($scenario['files'][$root])) {
            return [null, 'find: '.($start ?? '.').': No such file or directory'];
        }

        $found = [];
        $prefix = $root === '/' ? '/' : $root.'/';
        foreach ([...array_map(fn ($d) => [$d, 'd'], array_keys($dirs)), ...array_map(fn ($f) => [$f, 'f'], array_keys($scenario['files']))] as [$p, $kind]) {
            if (($p !== $root && ! str_starts_with($p, $prefix)) || ($type && $type !== $kind)) {
                continue;
            }
            if ($name !== null && ! fnmatch($name, basename($p))) {
                continue;
            }
            $shown = $start === null || ! str_starts_with($start, '/') ? $this->relative($p, $cwd, $start ?? '.') : $p;
            $found[] = $shown;
        }
        sort($found, SORT_STRING);

        return [$this->join($found), null];
    }

    /** @return array{0: int, 1: list<string>, 2: ?string} */
    private function lineCount(string $name, array $args): array
    {
        $count = 10;
        $operands = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if ($arg === '-n') {
                $arg = '-'.($args[++$i] ?? '');
            }
            if (preg_match('/^-(\d{1,5})$/', $arg, $m)) {
                $count = (int) $m[1];
            } elseif (str_starts_with($arg, '-')) {
                return [0, [], "{$name}: unsupported option {$arg} (use -n NUMBER)"];
            } else {
                $operands[] = $arg;
            }
        }

        return [$count, $operands, null];
    }

    /**
     * Single-letter flags from the allowed set, then operands.
     *
     * @return array{0: list<string>|string, 1: list<string>} flags or an error, and operands
     */
    private function flags(array $args, string $allowed): array
    {
        $flags = [];
        $operands = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '-') && strlen($arg) > 1) {
                foreach (str_split(substr($arg, 1)) as $f) {
                    if (! str_contains($allowed, $f)) {
                        return ["unsupported option -{$f} (supported: -".implode(' -', str_split($allowed)).')', []];
                    }
                    $flags[] = $f;
                }
            } else {
                $operands[] = $arg;
            }
        }

        return [array_values(array_unique($flags)), $operands];
    }

    /** Every directory: '/', the parents of every file, and any listed explicitly. */
    private function dirs(array $scenario, ?array $files = null): array
    {
        $dirs = ['/' => true];
        foreach ([...array_keys($files ?? $scenario['files'] ?? []), ...array_map(fn ($d) => $d.'/x', $scenario['dirs'] ?? [])] as $p) {
            for ($d = dirname($p); $d !== '/' && $d !== '.'; $d = dirname($d)) {
                $dirs[$d] = true;
            }
        }

        return $dirs;
    }

    /** Resolves ~, relative paths, "." and ".." to a normalized absolute path. */
    private function normalize(string $path, string $cwd, string $home = '/'): string
    {
        if ($path === '~' || str_starts_with($path, '~/')) {
            $path = $home.substr($path, 1);
        }
        $absolute = str_starts_with($path, '/') ? $path : rtrim($cwd, '/').'/'.$path;
        $parts = [];
        foreach (explode('/', $absolute) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            $part === '..' ? array_pop($parts) : $parts[] = $part;
        }

        return '/'.implode('/', $parts);
    }

    private function relative(string $path, string $cwd, string $start): string
    {
        $base = $this->normalize($start, $cwd);
        $rest = $path === $base ? '' : substr($path, strlen(rtrim($base, '/')) + 1);

        return rtrim($start, '/').($rest === '' ? '' : '/'.$rest);
    }

    private function longLine(string $name, int $size, bool $dir): string
    {
        return ($dir ? 'drwxr-xr-x' : '-rw-r--r--').' '.str_pad((string) $size, 6, ' ', STR_PAD_LEFT).' '.$name;
    }

    private function prompt(array $scenario, string $cwd): string
    {
        $shown = $cwd === $scenario['home'] ? '~' : (str_starts_with($cwd, $scenario['home'].'/') ? '~'.substr($cwd, strlen($scenario['home'])) : $cwd);

        return "{$scenario['user']}@{$scenario['hostname']}:{$shown}$";
    }

    /** @return list<string> */
    private function lines(string $text): array
    {
        $text = rtrim($text, "\n");

        return $text === '' ? [] : explode("\n", $text);
    }

    private function join(array $lines): string
    {
        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }
}
