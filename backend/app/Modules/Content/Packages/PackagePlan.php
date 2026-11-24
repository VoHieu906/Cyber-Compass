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
    /** A badly broken package can fail thousands of checks; the report keeps the first ones readable. */
    public const MAX_REPORTED_ERRORS = 100;

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
        [$errors, $more] = self::capped($this->errors);

        return [
            'kind' => $this->kind,
            'generator' => $this->generator,
            'valid' => $this->valid(),
            'errors' => $errors,
            'more_errors' => $more,
            'warnings' => $this->warnings,
            'documents' => array_map(fn (array $d) => array_diff_key($d, ['snapshot' => true, 'model' => true]), $this->documents),
        ];
    }

    /**
     * The first MAX_REPORTED_ERRORS messages, still keyed by place, and how many were left out.
     *
     * @param  array<string, list<string>>  $errors
     * @return array{0: array<string, list<string>>, 1: int}
     */
    public static function capped(array $errors): array
    {
        $kept = [];
        $count = 0;
        foreach ($errors as $path => $messages) {
            foreach ($messages as $message) {
                if ($count++ < self::MAX_REPORTED_ERRORS) {
                    $kept[$path][] = $message;
                }
            }
        }

        return [$kept, max(0, $count - self::MAX_REPORTED_ERRORS)];
    }

    /** The report of a package that could not even be read (a broken zip, invalid JSON). */
    public static function unreadable(array $errors): array
    {
        [$kept, $more] = self::capped($errors);

        return ['kind' => null, 'generator' => null, 'valid' => false, 'errors' => $kept, 'more_errors' => $more, 'warnings' => [], 'documents' => []];
    }
}
