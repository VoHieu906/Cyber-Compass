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

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, UploadedFile $file, string $alt): LessonMedia
    {
        [$bytes, $width, $height] = $this->webp($file);

        $uuid = (string) Str::uuid();
        $path = "lesson-media/{$uuid}.webp";
        $disk = Storage::disk(config('cybercompass.uploads_disk'));
        $disk->put($path, $bytes, ['visibility' => 'public', 'ContentType' => 'image/webp']);

        try {
            return DB::transaction(function () use ($actor, $uuid, $path, $alt, $width, $height, $bytes) {
                $media = LessonMedia::query()->create([
                    'uuid' => $uuid,
                    'path' => $path,
                    'alt' => $alt,
                    'width' => $width,
                    'height' => $height,
                    'size_bytes' => strlen($bytes),
                    'uploaded_by' => $actor->id,
                ]);
                $this->audit->record($actor, 'media.uploaded', 'media', $media->id, ['alt' => $alt]);

                return $media;
            });
        } catch (\Throwable $e) {
            $disk->delete($path);
            throw $e;
        }
    }

    /** @return array{0: string, 1: int, 2: int} */
    private function webp(UploadedFile $file): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
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
