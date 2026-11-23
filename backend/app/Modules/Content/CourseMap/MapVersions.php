<?php

namespace App\Modules\Content\CourseMap;

/**
 * Schema versions of course maps and lesson architectures (F3). Both carry `version`.
 * A schema change bumps CURRENT and adds one step here that turns version n into n+1; every
 * reader upgrades on the way in (snapshots, content files, the admin save), and
 * `content:check-graphs --upgrade` rewrites the stored live rows. Procedure:
 * docs/CONTENT_AUTHORING_GUIDE.md "Maintaining a course map".
 */
final class MapVersions
{
    public const CURRENT = 1;

    /**
     * version => fn (array $data): array, returning the data at version + 1.
     *
     * @return array<int, \Closure(array<string, mixed>): array<string, mixed>>
     */
    private static function steps(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, list<string>>, 1: array<string, mixed>}
     */
    public static function upgrade(array $data, string $prefix): array
    {
        $version = $data['version'] ?? null;
        if (! is_int($version) || $version < 1) {
            return [["{$prefix}.version" => [__('This field is required.')]], $data];
        }
        if ($version > self::CURRENT) {
            return [["{$prefix}.version" => [__('Version :v is newer than this release understands.', ['v' => $version])]], $data];
        }
        $steps = self::steps();
        for (; $version < self::CURRENT; $version++) {
            $data = $steps[$version]($data);
            $data['version'] = $version + 1;
        }

        return [[], $data];
    }
}
