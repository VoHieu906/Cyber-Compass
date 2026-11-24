<?php

namespace App\Modules\Content\Packages;

use App\Models\LessonMedia;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Writes an exported package as a .ccpkg.zip (D67, D70): `manifest.json` plus every image it names
 * under `media/`. Images are found without knowing any block type: any string in the package that
 * is the UUID of an uploaded image becomes {"file": "media/<uuid>.webp", "alt": "..."}, the form an
 * import reads back, so the zip also imports on another installation.
 */
final class PackageZip
{
    /** @return array{kind: string, documents: int, images: int} */
    public function write(array $envelope, string $file): array
    {
        $media = LessonMedia::query()->whereIn('uuid', self::uuids($envelope))->get()->keyBy('uuid');
        $disk = Storage::disk(config('cybercompass.uploads_disk'));

        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create {$file}.");
        }
        $written = 0;
        foreach ($media as $uuid => $image) {
            $bytes = $disk->get($image->path);
            if ($bytes !== null) {
                $zip->addFromString("media/{$uuid}.webp", $bytes);
                $written++;
            }
        }
        $envelope = self::withFiles($envelope, $media->map(fn (LessonMedia $m) => ['file' => "media/{$m->uuid}.webp", 'alt' => $m->alt])->all());
        $zip->addFromString('manifest.json', PackageExporter::json($envelope));
        $zip->close();

        $documents = $envelope['kind'] === 'course' ? count($envelope['documents']) : 1;

        return ['kind' => $envelope['kind'], 'documents' => $documents, 'images' => $written];
    }

    /** @return list<string> every UUID-shaped string in the package */
    private static function uuids(mixed $node): array
    {
        if (is_string($node)) {
            return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $node) ? [strtolower($node)] : [];
        }
        if (! is_array($node)) {
            return [];
        }

        return array_values(array_unique(array_merge([], ...array_map(self::uuids(...), array_values($node)))));
    }

    /** @param  array<string, array{file: string, alt: string}>  $refs */
    private static function withFiles(mixed $node, array $refs): mixed
    {
        if (is_string($node)) {
            return $refs[strtolower($node)] ?? $node;
        }
        if (! is_array($node)) {
            return $node;
        }
        foreach ($node as $key => $value) {
            // Section uuids in operations name sections, never images; leave them as they are.
            $node[$key] = $key === 'uuid' ? $value : self::withFiles($value, $refs);
        }

        return $node;
    }
}
