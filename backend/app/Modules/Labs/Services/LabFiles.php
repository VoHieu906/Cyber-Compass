<?php

namespace App\Modules\Labs\Services;

use InvalidArgumentException;

/**
 * Reads the lab content files (D73): `labs.php`, hand-written and keyed by slug, plus
 * `labs/<slug>.php`, one file per lab, which is where `content:export --lab` writes a lab an admin
 * edited. When both define a slug the per-lab file wins, because it is the newer, exported copy;
 * the slug is reported so the stale entry in `labs.php` gets removed. A per-lab file must be named
 * after the one slug it holds.
 */
final class LabFiles
{
    /** @return array{labs: array<string, array<string, mixed>>, shadowed: list<string>} */
    public static function read(?string $base = null): array
    {
        $base ??= database_path('seeders/content');
        $labs = is_file("{$base}/labs.php") ? require "{$base}/labs.php" : [];
        $shadowed = [];
        $files = glob("{$base}/labs/*.php") ?: [];
        sort($files);
        foreach ($files as $file) {
            $slug = basename($file, '.php');
            $content = require $file;
            if (! is_array($content) || array_keys($content) !== [$slug]) {
                throw new InvalidArgumentException('The lab file labs/'.basename($file)." must return an array with exactly one entry, keyed \"{$slug}\" like the file name.");
            }
            if (isset($labs[$slug])) {
                $shadowed[] = $slug;
            }
            $labs[$slug] = $content[$slug];
        }

        return ['labs' => $labs, 'shadowed' => $shadowed];
    }
}
