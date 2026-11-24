<?php

namespace App\Modules\Content\Packages;

use App\Modules\Content\Services\ContentFileMedia;

/**
 * What a content package would do (D67), worked out without writing anything: per document, the
 * validated draft snapshot and the section changes, plus errors (by place in the package) and
 * warnings. The preview shows it; ImportPackage writes it.
 */
final class PackagePlan
{
    /** @var array<string, list<string>> package path => messages */
    public array $errors = [];

    /** @var list<array{path: string, message: string}> */
    public array $warnings = [];

    /**
     * @var list<array{type: string, slug: string, title: string, id: ?int, is_new: bool, chapter: ?array{id: int, title: string}, lock_version: int, fingerprint: ?string, snapshot: ?array, changes: list<array{change: string, key: string, uuid: string, type: string, heading: ?string}>, model: mixed}>
     */
    public array $documents = [];

    public function __construct(
        public readonly string $kind,
        public readonly ?string $generator,
        public readonly ContentFileMedia $media,
    ) {}

    public function valid(): bool
    {
        return $this->errors === [];
    }

    public function error(string $path, string $message): void
    {
        $this->errors[$path][] = $message;
    }

    public function warn(string $path, string $message): void
    {
        $this->warnings[] = ['path' => $path, 'message' => $message];
    }

    /** The preview: everything but the models and snapshots. */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'generator' => $this->generator,
            'valid' => $this->valid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'documents' => array_map(fn (array $d) => array_diff_key($d, ['snapshot' => true, 'model' => true]), $this->documents),
        ];
    }

    /** Plain text a chatbot can act on: one line per problem, the place first. */
    public function errorText(): string
    {
        $lines = [];
        foreach ($this->errors as $path => $messages) {
            foreach ($messages as $message) {
                $lines[] = "- {$path}: {$message}";
            }
        }

        return $lines === [] ? '' : "The package was rejected. Fix these and send the whole package again:\n".implode("\n", $lines);
    }
}
