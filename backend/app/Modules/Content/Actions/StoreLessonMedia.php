<?php

namespace App\Modules\Content\Actions;

use App\Models\LessonMedia;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Stores a lesson image. Like avatars, it is decoded and re-encoded to WebP with GD, so
 * only pixels survive (no metadata, scripts or polyglot payloads), and it is downscaled
 * to a reading width. SVG is rejected by the request before it gets here.
 */
final class StoreLessonMedia
{
    public const MAX_WIDTH = 1600;

    /**
     * No visibility option: a private S3 bucket with ACLs disabled (the AWS default, D41) refuses
     * the public-read ACL it would send; the local `public` disk is public by its own config.
     * Names are random and never reused, so the file can be cached for good (CloudFront, D41).
     */
    private const PUT_OPTIONS = ['ContentType' => 'image/webp', 'CacheControl' => 'public, max-age=31536000, immutable'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, UploadedFile $file, string $alt): LessonMedia
    {
        return $this->store((string) file_get_contents($file->getRealPath()), $alt, (string) Str::uuid(), null, $actor,
            fn (LessonMedia $media) => $this->audit->record($actor, 'media.uploaded', 'media', $media->id, ['alt' => $alt]));
    }

    /**
     * Stores an image from a content file (ARCHITECTURE D56) under the UUID the importer derived
     * from its path and bytes, through the same re-encoding as uploads. Call inside the import
     * transaction; a rolled-back import leaves only a file that the next import overwrites.
     */
    public function importFile(string $bytes, string $alt, string $uuid, string $source): LessonMedia
    {
        return $this->store($bytes, $alt, $uuid, $source, null);
    }

    private function store(string $original, string $alt, string $uuid, ?string $source, ?User $uploader, ?\Closure $audit = null): LessonMedia
    {
        [$bytes, $width, $height] = $this->webp($original);

        $path = "lesson-media/{$uuid}.webp";
        $disk = Storage::disk(config('cybercompass.uploads_disk'));
        $disk->put($path, $bytes, self::PUT_OPTIONS);

        try {
            return DB::transaction(function () use ($uuid, $path, $source, $alt, $width, $height, $bytes, $uploader, $audit) {
                $media = LessonMedia::query()->create([
                    'uuid' => $uuid,
                    'path' => $path,
                    'source' => $source,
                    'alt' => $alt,
                    'width' => $width,
                    'height' => $height,
                    'size_bytes' => strlen($bytes),
                    'uploaded_by' => $uploader?->id,
                ]);
                $audit && $audit($media);

                return $media;
            });
        } catch (\Throwable $e) {
            $disk->delete($path);
            throw $e;
        }
    }

    /** @return array{0: string, 1: int, 2: int} */
    private function webp(string $original): array
    {
        $source = @imagecreatefromstring($original);
        if ($source === false) {
            throw ValidationException::withMessages(['image' => __('validation.image', ['attribute' => 'image'])]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width > self::MAX_WIDTH) {
            $height = (int) round($height * self::MAX_WIDTH / $width);
            $width = self::MAX_WIDTH;
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        $ok = imagewebp($canvas, null, 82);
        $bytes = (string) ob_get_clean();
        if (! $ok || $bytes === '') {
            throw new RuntimeException('Could not encode the image.');
        }

        return [$bytes, $width, $height];
    }
}
