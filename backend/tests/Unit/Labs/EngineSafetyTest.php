<?php

/*
| ARCHITECTURE §7.1: lab engines are pure in-memory simulations. This test reads every file
| under app/Modules/Labs/Engines token by token and fails if any of them calls a process,
| file system or network function, uses backticks, or includes another file.
*/

const FORBIDDEN_CALLS = [
    // processes
    'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec', 'pcntl_fork', 'proc_nice', 'posix_kill',
    // the real file system
    'fopen', 'file', 'file_get_contents', 'file_put_contents', 'readfile', 'fread', 'fwrite', 'fputs', 'scandir', 'glob', 'opendir',
    'readdir', 'unlink', 'rename', 'copy', 'mkdir', 'rmdir', 'touch', 'chmod', 'chown', 'symlink', 'link', 'tempnam', 'tmpfile',
    'is_file', 'is_dir', 'file_exists', 'realpath', 'parse_ini_file', 'highlight_file', 'show_source', 'move_uploaded_file',
    // the network
    'fsockopen', 'pfsockopen', 'stream_socket_client', 'stream_socket_server', 'socket_create', 'curl_init', 'curl_exec', 'mail',
    // dynamic code
    'eval', 'assert', 'create_function', 'call_user_func', 'call_user_func_array', 'extract', 'putenv', 'dl',
];

function engineFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app/Modules/Labs/Engines', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

/** @return list<string> forbidden uses in a file, as "name on line n" */
function forbiddenUses(string $code): array
{
    $tokens = array_values(array_filter(token_get_all($code), fn ($t) => ! is_array($t) || ! in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    $found = [];
    foreach ($tokens as $i => $token) {
        if ($token === '`') {
            $found[] = 'backtick';

            continue;
        }
        if (! is_array($token)) {
            continue;
        }
        if (in_array($token[0], [T_EVAL, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
            $found[] = strtolower($token[1])." on line {$token[2]}";

            continue;
        }
        $isName = in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true);
        $called = ($tokens[$i + 1] ?? null) === '(';
        $previous = $tokens[$i - 1] ?? null;
        // A method, a static method or a function declaration with the same name is not the PHP function.
        $member = is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true);
        if ($isName && $called && ! $member && in_array(strtolower(ltrim($token[1], '\\')), FORBIDDEN_CALLS, true)) {
            $found[] = "{$token[1]}() on line {$token[2]}";
        }
    }

    return $found;
}

it('scans every engine file', function () {
    expect(engineFiles())->not->toBeEmpty()
        ->and(array_map(fn ($f) => basename($f), engineFiles()))->toContain('LinuxShellEngine.php', 'FirewallRulesEngine.php', 'HttpInspectorEngine.php');
});

it('finds no process, file system, network or dynamic code use in the engines', function (string $file) {
    expect(forbiddenUses(file_get_contents($file)))->toBe([]);
})->with(fn () => array_combine(array_map(fn ($f) => basename($f), engineFiles()), array_map(fn ($f) => [$f], engineFiles())));

it('would catch a forbidden call', function (string $code, string $expected) {
    expect(implode(' | ', forbiddenUses("<?php\n".$code)))->toContain($expected);
})->with([
    ['shell_exec("ls");', 'shell_exec()'],
    ['\exec("id");', 'exec()'],
    ['$x = `id`;', 'backtick'],
    ['file_get_contents("/etc/passwd");', 'file_get_contents()'],
    ['proc_open("sh", [], $p);', 'proc_open()'],
    ['require "x.php";', 'require'],
]);

it('allows methods that share a name with a forbidden function', function () {
    expect(forbiddenUses("<?php\n\$this->system();\nFoo::exec();\nfunction file() {}"))->toBe([]);
});
