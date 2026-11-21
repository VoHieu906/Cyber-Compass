<?php

use App\Modules\Labs\Engines\LabActionRejected;
use App\Modules\Labs\Engines\LabLimits;
use App\Modules\Labs\Engines\Shell\LinuxShellEngine;
use App\Modules\Labs\Engines\Shell\ShellParser;

/*
| The lab shell (ARCHITECTURE §7.1): whitelisted commands over a virtual file system, friendly
| refusals for shell syntax, literal grep with a bounded -E, and capped output.
*/

beforeEach(function () {
    $this->engine = new LinuxShellEngine(new ShellParser);
    $this->scenario = [
        'hostname' => 'web-01',
        'user' => 'analyst',
        'home' => '/home/analyst',
        'cwd' => '/home/analyst',
        'files' => [
            '/home/analyst/notes.txt' => "first\nsecond\n",
            '/home/analyst/.hidden' => "secret\n",
            '/var/log/auth.log' => "Failed password for root from 1.2.3.4\nAccepted password for bob from 5.6.7.8\nFailed password for admin from 1.2.3.4\nfailed PASSWORD for x from 9.9.9.9\n",
        ],
        'dirs' => ['/tmp'],
        'checkpoints' => [['id' => 'read', 'kind' => 'read', 'path' => '/var/log/auth.log', 'title' => 'Read it']],
    ];
    $this->state = $this->engine->initialState($this->scenario);
});

/** Runs commands in order, returning the last output; the state carries over. */
function sh(object $test, string ...$commands): array
{
    $output = [];
    foreach ($commands as $command) {
        $result = $test->engine->apply($test->scenario, $test->state, ['type' => 'command', 'command' => $command]);
        $test->state = $result['state'];
        $output = $result['output'];
    }

    return $output;
}

it('navigates and lists the virtual file system', function () {
    expect(sh($this, 'pwd')['text'])->toBe('/home/analyst')
        ->and(sh($this, 'ls')['text'])->toBe('notes.txt')
        ->and(sh($this, 'ls -a')['text'])->toBe(".hidden\nnotes.txt")
        ->and(sh($this, 'cd /var/log', 'pwd')['text'])->toBe('/var/log')
        ->and(sh($this, 'cd ..', 'ls')['text'])->toBe('log/')
        ->and(sh($this, 'cd ~', 'pwd')['text'])->toBe('/home/analyst')
        ->and(sh($this, 'cd /nowhere'))->toMatchArray(['text' => 'cd: /nowhere: No such directory', 'error' => true])
        ->and($this->engine->view($this->scenario, $this->state)['prompt'])->toBe('analyst@web-01:~$');
});

it('records files read for read checkpoints', function () {
    $checkpoint = $this->scenario['checkpoints'][0];
    expect($this->engine->checkpointPassed($this->scenario, $this->state, $checkpoint))->toBeFalse();
    sh($this, 'cd /var/log', 'head -n 1 auth.log');
    expect($this->engine->checkpointPassed($this->scenario, $this->state, $checkpoint))->toBeTrue();
});

it('greps literally by default, with -i -n -c -v', function () {
    sh($this, 'cd /var/log');
    expect(sh($this, 'grep "Failed password" auth.log')['text'])->toBe("Failed password for root from 1.2.3.4\nFailed password for admin from 1.2.3.4")
        ->and(sh($this, 'grep -ic "failed password" auth.log')['text'])->toBe('3')
        ->and(sh($this, 'grep -n Accepted auth.log')['text'])->toBe('2:Accepted password for bob from 5.6.7.8')
        ->and(sh($this, 'grep -vc Failed auth.log')['text'])->toBe('2')
        // A dot is a dot without -E.
        ->and(sh($this, 'grep -c 1.2.3 auth.log')['text'])->toBe('2')
        ->and(sh($this, 'grep -c 1x2 auth.log')['text'])->toBe('0');
});

it('pipes up to five stages', function () {
    sh($this, 'cd /var/log');
    expect(sh($this, 'grep 1.2.3.4 auth.log | grep -c Failed')['text'])->toBe('2')
        ->and(sh($this, 'grep -i failed auth.log | cut -d " " -f 6 | sort | uniq -c | sort -rn')['text'])->toBe("      2 1.2.3.4\n      1 9.9.9.9")
        ->and(sh($this, 'cat auth.log | wc -l')['text'])->toBe('4')
        ->and(sh($this, 'cat auth.log | tail -n 1')['text'])->toBe('failed PASSWORD for x from 9.9.9.9')
        ->and(sh($this, 'cat a | cat | cat | cat | cat | cat'))->toMatchArray(['text' => 'too many pipe stages (at most 5)', 'error' => true]);
});

it('finds files by name, and refuses find actions', function () {
    expect(sh($this, 'find / -name "*.log"')['text'])->toBe('/var/log/auth.log')
        ->and(sh($this, 'find /var -type d')['text'])->toBe("/var\n/var/log")
        ->and(sh($this, 'find / -name x -exec rm {} ;')['error'])->toBeTrue()
        ->and(sh($this, 'find / -delete')['text'])->toContain('find: -delete is not supported');
});

it('refuses shell syntax with a message, never an exception', function (string $command, string $message) {
    $output = sh($this, $command);

    expect($output['error'])->toBeTrue()->and($output['text'])->toContain($message);
})->with([
    'chaining' => ['ls; cat notes.txt', 'chaining commands with ";"'],
    'and' => ['ls && pwd', '"&&"'],
    'or' => ['ls || pwd', '"||"'],
    'background' => ['ls &', '"&&"'],
    'redirect out' => ['cat notes.txt > copy', 'redirecting output'],
    'redirect in' => ['grep x < notes.txt', 'redirecting input'],
    'backticks' => ['echo `id`', 'backticks'],
    'substitution' => ['echo $(id)', 'variables and "$(...)"'],
    'substitution in double quotes' => ['echo "$(id)"', 'variables and "$(...)"'],
    'variable' => ['echo ${HOME}', 'variables'],
    'subshell' => ['(ls)', 'subshells'],
    'unclosed quote' => ['echo "hi', 'unclosed quote'],
    'empty pipe end' => ['ls |', 'nothing after it'],
    'leading pipe' => ['| ls', 'syntax error near "|"'],
    'unknown command' => ['nmap 10.0.0.1', 'command not found'],
    'unavailable command' => ['rm -rf /', 'not available in this lab'],
    'unsupported flag' => ['grep -P x notes.txt', 'unsupported option -P'],
]);

it('keeps single-quoted text literal', function () {
    expect(sh($this, "echo 'a; b \$(c) `d` > e'")['text'])->toBe('a; b $(c) `d` > e');
});

it('refuses overlong commands', function () {
    expect(sh($this, 'echo '.str_repeat('a', LabLimits::MAX_COMMAND_LENGTH)))->toMatchArray(['error' => true, 'text' => 'command too long (at most 500 characters)']);
});

it('runs -E patterns within the length and backtracking limits (ReDoS)', function () {
    $this->scenario['files']['/tmp/long'] = str_repeat('a', 5000)."b\n";
    sh($this, 'cd /var/log');
    expect(sh($this, "grep -E '^(Failed|Accepted) password for (root|bob)' auth.log")['text'])->toBe("Failed password for root from 1.2.3.4\nAccepted password for bob from 5.6.7.8")
        ->and(sh($this, 'grep -E "'.str_repeat('a', 101).'" auth.log')['text'])->toBe('grep: pattern too long (at most 100 characters)')
        ->and(sh($this, 'grep -E "(unclosed" auth.log')['text'])->toBe('grep: invalid pattern');

    $started = microtime(true);
    $output = sh($this, "grep -E '(a+)+\$' /tmp/long");
    expect($output)->toMatchArray(['text' => 'grep: pattern too complex', 'error' => true])
        ->and(microtime(true) - $started)->toBeLessThan(2.0)
        ->and(ini_get('pcre.backtrack_limit'))->not->toBe((string) LabLimits::PCRE_BACKTRACK_LIMIT);
});

it('caps output at 64 KB and keeps a bounded history', function () {
    $this->scenario['files']['/tmp/big'] = str_repeat(str_repeat('x', 99)."\n", 1200);
    $output = sh($this, 'cat /tmp/big /tmp/big');

    expect(strlen($output['text']))->toBeLessThan(LabLimits::MAX_OUTPUT_BYTES + 100)
        ->and($output['text'])->toEndWith('[output truncated at 64 KB]');

    for ($i = 0; $i < 45; $i++) {
        sh($this, 'pwd');
    }
    expect($this->state['history'])->toHaveCount(40)
        ->and(strlen(json_encode($this->state)))->toBeLessThan(LabLimits::MAX_STATE_BYTES);
});

it('clears the screen and rejects malformed actions', function () {
    sh($this, 'pwd');
    expect(sh($this, 'clear'))->toMatchArray(['clear' => true])
        ->and($this->state['history'])->toBe([]);
    expect(fn () => $this->engine->apply($this->scenario, $this->state, ['type' => 'command']))->toThrow(LabActionRejected::class);
});

it('validates scenarios', function () {
    expect($this->engine->validateScenario($this->scenario))->toBe([])
        ->and(implode(' | ', $this->engine->validateScenario([...$this->scenario, 'home' => '/nope', 'files' => ['relative.txt' => 'x']])))
        ->toContain('must be absolute')->toContain('home must be a directory');
});
