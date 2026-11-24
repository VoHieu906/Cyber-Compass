<?php

namespace App\Modules\Content\Packages;

use App\Modules\Content\Services\ContentFileMedia;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Opens a content package (D67): a `*.ccpkg.json` document, or a `*.ccpkg.zip` with
 * `manifest.json` at its root. Every limit in `cybercompass.content_packages` is checked before
 * anything is unpacked or decoded; zip entries are unpacked one at a time into a private folder.
 * In a zip, `{"$file": "path"}` anywhere is replaced by that entry: parsed when it ends in .json,
 * otherwise its UTF-8 text.
 *
 * The caller owns the folder in `root` and must call cleanup() when done, whatever happens.
 * Errors are keyed `package` or by the place in the document, never with raw file content.
 */
final class PackageReader
{
    /** @return array{envelope: array<string, mixed>, root: ?string} */
    public function read(string $path, string $name): array
    {
        $limits = config('cybercompass.content_packages');
        $size = @filesize($path);
        if ($size === false) {
            self::fail('package', 'The package file could not be read.');
        }
        if ($size > $limits['max_bytes']) {
            self::fail('package', 'The package is larger than '.self::mb($limits['max_bytes']).'.');
        }

        if (str_ends_with(strtolower($name), '.zip')) {
            $root = $this->unzip($path, $limits);
            try {
                $envelope = $this->decode($root.'/manifest.json', 'manifest.json', $limits);
                $envelope = $this->resolveFiles($envelope, $root, $limits, 'manifest', 0);
            } catch (\Throwable $e) {
                self::cleanup($root);
                throw $e;
            }

            return ['envelope' => $envelope, 'root' => $root];
        }

        if (! str_ends_with(strtolower($name), '.json')) {
            self::fail('package', 'Upload a .ccpkg.json document or a .ccpkg.zip bundle.');
        }
        if ($size > $limits['max_document_bytes']) {
            self::fail('package', 'A JSON package can be at most '.self::mb($limits['max_document_bytes']).'; put large content in a zip bundle.');
        }
        $envelope = $this->decode($path, $name, $limits);
        $this->refuseFiles($envelope, '');

        return ['envelope' => $envelope, 'root' => null];
    }

    /** Validates text pasted into the admin (validate-only mode): the same rules as a .json upload. */
    public function fromString(string $json): array
    {
        $limits = config('cybercompass.content_packages');
        if (strlen($json) > $limits['max_document_bytes']) {
            self::fail('package', 'A JSON package can be at most '.self::mb($limits['max_document_bytes']).'.');
        }
        $envelope = $this->parse($json, 'package', $limits);
        $this->refuseFiles($envelope, '');

        return ['envelope' => $envelope, 'root' => null];
    }

    public static function cleanup(?string $root): void
    {
        if ($root !== null && is_dir($root)) {
            File::deleteDirectory($root);
        }
    }

    /** The folder temporary packages are unpacked into; a scheduled cleanup removes what a crash leaves. */
    public static function workDir(): string
    {
        return storage_path('app/tmp/packages');
    }

    private function unzip(string $path, array $limits): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            self::fail('package', 'The file is not a valid zip archive.');
        }

        $root = self::workDir().'/'.Str::uuid();
        File::ensureDirectoryExists($root, 0700);
        $allowed = [...$limits['text_extensions'], ...ContentFileMedia::EXTENSIONS];
        $total = 0;

        try {
            if ($zip->numFiles > $limits['max_entries']) {
                self::fail('package', "The zip has {$zip->numFiles} entries; at most {$limits['max_entries']} are allowed.");
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entry = (string) $stat['name'];
                if (str_ends_with($entry, '/')) {
                    continue; // a folder
                }
                $at = 'zip.'.self::label($entry);
                if (! self::safeEntryName($entry)) {
                    self::fail($at, 'Entry paths must be relative, use forward slashes and stay inside the package.');
                }
                $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (! in_array($extension, $allowed, true)) {
                    self::fail($at, "Files of type .{$extension} are not allowed in a package.");
                }
                // Declared sizes are checked first and then enforced while copying, since headers can lie.
                $declared = (int) $stat['size'];
                if ($declared > 1024 * 1024 && $stat['comp_size'] > 0 && $declared / $stat['comp_size'] > $limits['max_ratio']) {
                    self::fail($at, 'This entry compresses suspiciously well (a zip bomb?) and was refused.');
                }
                if ($total + $declared > $limits['max_uncompressed_bytes']) {
                    self::fail('package', 'The unpacked package would be larger than '.self::mb($limits['max_uncompressed_bytes']).'.');
                }
                $total += $this->extract($zip, $i, "{$root}/{$entry}", $declared, $at);
            }
        } catch (\Throwable $e) {
            $zip->close();
            self::cleanup($root);
            throw $e;
        }
        $zip->close();

        if (! is_file("{$root}/manifest.json")) {
            self::cleanup($root);
            self::fail('package', 'A zip package needs manifest.json at its root.');
        }

        return $root;
    }

    /** Copies one entry in chunks, never more than it declared. @return int bytes written */
    private function extract(ZipArchive $zip, int $index, string $target, int $declared, string $at): int
    {
        File::ensureDirectoryExists(dirname($target), 0700);
        $in = $zip->getStream($zip->getNameIndex($index));
        $out = fopen($target, 'xb');
        if ($in === false || $out === false) {
            self::fail($at, 'This entry could not be unpacked.');
        }
        $written = 0;
        try {
            while (! feof($in)) {
                $chunk = fread($in, 1024 * 1024);
                if ($chunk === false) {
                    self::fail($at, 'This entry could not be unpacked.');
                }
                $written += strlen($chunk);
                if ($written > $declared) {
                    self::fail($at, 'This entry is larger than the zip says it is and was refused.');
                }
                fwrite($out, $chunk);
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        return $written;
    }

    private static function safeEntryName(string $name): bool
    {
        return $name !== ''
            && ! str_contains($name, "\0")
            && ! str_contains($name, '\\')
            && ! str_starts_with($name, '/')
            && ! preg_match('/^[a-zA-Z]:/', $name)
            && ! in_array('..', explode('/', $name), true)
            && ! in_array('.', explode('/', $name), true);
    }

    private function decode(string $file, string $label, array $limits): array
    {
        if (filesize($file) > $limits['max_document_bytes']) {
            self::fail(self::label($label), 'A JSON document can be at most '.self::mb($limits['max_document_bytes']).'.');
        }

        return $this->parse((string) file_get_contents($file), self::label($label), $limits);
    }

    private function parse(string $json, string $at, array $limits): array
    {
        $json = self::withoutBom($json);
        if (! mb_check_encoding($json, 'UTF-8')) {
            self::fail($at, 'The document is not valid UTF-8.');
        }
        try {
            $data = json_decode($json, true, $limits['max_json_depth'], JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::fail($at, 'This is not valid JSON ('.$e->getMessage().'). Check quotes, commas and escaped line breaks in long text.');
        }
        if (! is_array($data) || array_is_list($data)) {
            self::fail($at, 'The document must be a JSON object.');
        }

        return $data;
    }

    /** Replaces every {"$file": "..."} in a zip's documents. Nested .json files may use it again, a few levels deep. */
    private function resolveFiles(mixed $node, string $root, array $limits, string $at, int $level): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        if (array_keys($node) === ['$file']) {
            $path = $node['$file'];
            if (! is_string($path) || ! self::safeEntryName($path) || ! is_file("{$root}/{$path}")) {
                self::fail($at, 'The $file '.(is_string($path) ? '"'.self::label($path).'"' : '').' is not in the package.');
            }
            $full = "{$root}/{$path}";
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json') {
                if ($level >= 3) {
                    self::fail($at, 'JSON files may name other files with $file at most three levels deep.');
                }

                return $this->resolveFiles($this->decode($full, $path, $limits), $root, $limits, $at, $level + 1);
            }
            if (filesize($full) > $limits['max_text_bytes']) {
                self::fail($at, 'A $file text can be at most '.self::mb($limits['max_text_bytes']).'.');
            }
            $text = self::withoutBom((string) file_get_contents($full));
            if (! mb_check_encoding($text, 'UTF-8')) {
                self::fail($at, 'The $file "'.self::label($path).'" is not valid UTF-8 text.');
            }

            return str_replace("\r\n", "\n", $text);
        }
        foreach ($node as $key => $value) {
            $node[$key] = $this->resolveFiles($value, $root, $limits, "{$at}.{$key}", $level);
        }

        return $node;
    }

    private function refuseFiles(mixed $node, string $at): void
    {
        if (! is_array($node)) {
            return;
        }
        if (array_key_exists('$file', $node)) {
            self::fail(ltrim($at, '.') ?: 'package', '$file references work only inside a .ccpkg.zip bundle.');
        }
        foreach ($node as $key => $value) {
            $this->refuseFiles($value, "{$at}.{$key}");
        }
    }

    private static function withoutBom(string $text): string
    {
        return str_starts_with($text, "\u{FEFF}") ? substr($text, 3) : $text;
    }

    /** A file name safe to put in an error key or message: no dots splitting the path, at most 80 characters. */
    private static function label(string $name): string
    {
        return Str::limit(preg_replace('/[^A-Za-z0-9_\/-]+/', '_', $name), 80, '');
    }

    private static function mb(int $bytes): string
    {
        return round($bytes / 1024 / 1024).' MB';
    }

    private static function fail(string $at, string $message): never
    {
        throw ValidationException::withMessages([$at => [$message]]);
    }
}
