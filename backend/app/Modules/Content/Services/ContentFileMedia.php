<?php

namespace App\Modules\Content\Services;

use App\Models\LessonMedia;
use App\Modules\Content\Actions\StoreLessonMedia;
use Ramsey\Uuid\Uuid;

/**
 * Images named by content files (ARCHITECTURE D56). A content file may write
 * `['file' => 'posts/media/<post>/<name>.png', 'alt' => '...']` where a media UUID is expected
 * (a `media` block's `media`, a post's `cover`). The path is relative to database/seeders/content.
 *
 * resolve() checks the reference and returns the UUID derived from the path and the bytes, so an
 * unchanged file always maps to the same media row and a changed file gets a new one. store()
 * creates the rows that do not exist yet, through the upload pipeline (WebP, uploads_disk).
 */
final class ContentFileMedia
{
    /** Namespace for media UUIDs derived from "path#sha1". Never change it. */
    private const NAMESPACE = 'b4e2f1d0-7c3a-4f5e-9a8b-1d2c3e4f5a60';

    public const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /** @var array<string, array{path: string, alt: string, bytes: string}> uuid => pending image */
    private array $pending = [];

    public function __construct(private readonly StoreLessonMedia $media) {}

    public static function isReference(mixed $value): bool
    {
        return is_array($value) && array_key_exists('file', $value);
    }

    /**
     * @param  array<string, mixed>  $ref
     * @param  array<string, list<string>>  $errors  filled with messages keyed by $at
     */
    public function resolve(array $ref, string $at, array &$errors): ?string
    {
        $path = $ref['file'];
        $alt = trim((string) ($ref['alt'] ?? ''));
        $root = realpath(self::root());
        $full = is_string($path) ? realpath(self::root().'/'.$path) : false;

        if (! is_string($path) || $full === false || ! is_file($full) || ! str_starts_with($full, $root.DIRECTORY_SEPARATOR)) {
            $errors[$at][] = 'Image file not found under database/seeders/content: '.(is_string($path) ? $path : '?');

            return null;
        }
        if (! in_array(strtolower(pathinfo($full, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            $errors[$at][] = 'Images must be PNG, JPEG, WebP or GIF (SVG is rejected like uploads): '.$path;

            return null;
        }
        if ($alt === '' || mb_strlen($alt) > 300) {
            $errors[$at][] = 'Every image needs alt text of at most 300 characters.';

            return null;
        }

        $bytes = (string) file_get_contents($full);
        $uuid = Uuid::uuid5(self::NAMESPACE, $path.'#'.sha1($bytes))->toString();
        $this->pending[$uuid] = ['path' => $path, 'alt' => $alt, 'bytes' => $bytes];

        return $uuid;
    }

    /** Creates the media rows (and files) resolved so far that do not exist yet. Call inside the import transaction. */
    public function store(): void
    {
        $existing = LessonMedia::query()->whereIn('uuid', array_keys($this->pending))->get()->keyBy('uuid');
        foreach ($this->pending as $uuid => $image) {
            $row = $existing->get($uuid);
            if (! $row) {
                $this->media->importFile($image['bytes'], $image['alt'], $uuid, $image['path']);
            } elseif ($row->alt !== $image['alt']) {
                // Same file, new wording: the alt text lives with the image.
                $row->update(['alt' => $image['alt']]);
            }
        }
        $this->pending = [];
    }

    /** The reference content:export writes for a media row, or its UUID when it was uploaded. */
    public static function reference(?LessonMedia $media, string $uuid): string|array
    {
        return $media?->source ? ['file' => $media->source, 'alt' => $media->alt] : $uuid;
    }

    /** database/seeders/content; tests point `cybercompass.content_root` at a writable folder (CI checks out read-only for the app user). */
    public static function root(): string
    {
        return config('cybercompass.content_root') ?? database_path('seeders/content');
    }
}
