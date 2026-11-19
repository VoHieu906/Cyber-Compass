<?php

namespace App\Modules\Profile\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Stores an avatar as a square WebP. Decoding and re-encoding with GD drops anything
 * in the upload that is not pixels (metadata, embedded payloads, polyglot tricks),
 * and the stored name is random, so user input never reaches a path.
 */
final class StoreAvatar
{
    public function handle(User $user, UploadedFile $file): User
    {
        $size = (int) config('cybercompass.avatar.size');
        $webp = $this->squareWebp($file, $size);

        $disk = Storage::disk(config('cybercompass.uploads_disk'));
        $path = 'avatars/'.$user->getKey().'/'.Str::random(32).'.webp';
        $disk->put($path, $webp, ['visibility' => 'public', 'ContentType' => 'image/webp']);

        $profile = $user->profile()->firstOrCreate();
        $previous = $profile->avatar_path;
        $profile->update(['avatar_path' => $path]);

        if ($previous && $previous !== $path) {
            $disk->delete($previous);
        }

        return $user->load('profile');
    }

    public function remove(User $user): User
    {
        $profile = $user->profile()->firstOrCreate();

        if ($profile->avatar_path) {
            Storage::disk(config('cybercompass.uploads_disk'))->delete($profile->avatar_path);
            $profile->update(['avatar_path' => null]);
        }

        return $user->load('profile');
    }

    private function squareWebp(UploadedFile $file, int $size): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['avatar' => __('validation.image', ['attribute' => 'avatar'])]);
        }

        // Centre crop to a square, then scale.
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled(
            $canvas, $source, 0, 0,
            intdiv($width - $side, 2), intdiv($height - $side, 2),
            $size, $size, $side, $side,
        );

        ob_start();
        $ok = imagewebp($canvas, null, 85);
        $bytes = (string) ob_get_clean();

        if (! $ok || $bytes === '') {
            throw new RuntimeException('Could not encode the avatar.');
        }

        return $bytes;
    }
}
