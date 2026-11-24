<?php

namespace App\Modules\Content\Packages;

use App\Models\ContentTransfer;
use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Where package uploads and exports live between steps (D70): the `transfers` S3 bucket in
 * production, any local disk in development. Every step reads the file from here into a folder of
 * its own, because on AWS the api and the worker are separate containers with separate disks.
 *
 * Uploads come in parts: on S3 the browser PUTs each part to a presigned URL (multipart upload), so
 * no request body passes the ALB, CloudFront, the WAF or PHP; on a local disk the API takes the
 * parts itself. Both are described to the client by start().
 */
final class TransferStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('cybercompass.content_packages.disk'));
    }

    public function usesS3(): bool
    {
        return $this->disk() instanceof AwsS3V3Adapter;
    }

    public static function partBytes(): int
    {
        return (int) config('cybercompass.content_packages.part_bytes');
    }

    /**
     * Starts an upload and tells the client how to send the parts.
     *
     * @return array{mode: string, part_bytes: int, parts: list<array{number: int, url: ?string}>}
     */
    public function start(ContentTransfer $transfer): array
    {
        $count = max(1, (int) ceil($transfer->size_bytes / self::partBytes()));
        $transfer->parts = $count;

        if (! $this->usesS3()) {
            $transfer->save();

            return ['mode' => 'chunked', 'part_bytes' => self::partBytes(), 'parts' => array_map(fn ($n) => ['number' => $n, 'url' => null], range(1, $count))];
        }

        [$client, $bucket] = $this->s3();
        $transfer->upload_id = $client->createMultipartUpload(['Bucket' => $bucket, 'Key' => $transfer->path, 'ContentType' => 'application/octet-stream'])['UploadId'];
        $transfer->save();
        $parts = [];
        for ($n = 1; $n <= $count; $n++) {
            $command = $client->getCommand('UploadPart', ['Bucket' => $bucket, 'Key' => $transfer->path, 'UploadId' => $transfer->upload_id, 'PartNumber' => $n]);
            $parts[] = ['number' => $n, 'url' => (string) $client->createPresignedRequest($command, '+2 hours')->getUri()];
        }

        return ['mode' => 's3', 'part_bytes' => self::partBytes(), 'parts' => $parts];
    }

    /** One part sent to the API (local disks only). @param resource|string $body */
    public function putPart(ContentTransfer $transfer, int $number, $body): void
    {
        $this->disk()->put(self::partPath($transfer, $number), $body);
    }

    /**
     * Joins the parts into the package file and checks its size.
     *
     * @param  list<array{number: int, etag: string}>  $etags  S3 only: what each part PUT returned
     */
    public function complete(ContentTransfer $transfer, array $etags): void
    {
        $disk = $this->disk();
        if ($this->usesS3()) {
            [$client, $bucket] = $this->s3();
            usort($etags, fn ($a, $b) => $a['number'] <=> $b['number']);
            $client->completeMultipartUpload(['Bucket' => $bucket, 'Key' => $transfer->path, 'UploadId' => $transfer->upload_id,
                'MultipartUpload' => ['Parts' => array_map(fn ($p) => ['PartNumber' => $p['number'], 'ETag' => $p['etag']], $etags)]]);
        } else {
            $joined = fopen('php://temp/maxmemory:'.(4 * 1024 * 1024), 'w+b');
            for ($n = 1; $n <= $transfer->parts; $n++) {
                $part = $disk->readStream(self::partPath($transfer, $n));
                if ($part === null) {
                    throw ValidationException::withMessages(['parts' => ["Part {$n} was not uploaded."]]);
                }
                stream_copy_to_stream($part, $joined);
                fclose($part);
            }
            rewind($joined);
            $disk->writeStream($transfer->path, $joined);
            fclose($joined);
            $disk->deleteDirectory(dirname($transfer->path).'/parts');
        }

        if ($disk->size($transfer->path) !== $transfer->size_bytes) {
            $this->delete($transfer);
            throw ValidationException::withMessages(['parts' => ['The uploaded file does not have the size announced. Upload it again.']]);
        }
    }

    public function exists(ContentTransfer $transfer): bool
    {
        return $transfer->path !== null && $this->disk()->exists($transfer->path);
    }

    /** Copies the stored file into $dir (created) and returns its local path. */
    public function download(ContentTransfer $transfer, string $dir): string
    {
        File::ensureDirectoryExists($dir, 0700);
        $local = $dir.'/'.basename($transfer->path);
        $in = $this->disk()->readStream($transfer->path);
        $out = fopen($local, 'xb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        return $local;
    }

    public function put(ContentTransfer $transfer, string $localFile): void
    {
        $in = fopen($localFile, 'rb');
        $this->disk()->writeStream($transfer->path, $in);
        is_resource($in) && fclose($in);
    }

    /** A short-lived link to the file: presigned on S3, the API's own download route otherwise. */
    public function downloadUrl(ContentTransfer $transfer): string
    {
        return $this->usesS3()
            ? $this->disk()->temporaryUrl($transfer->path, now()->addMinutes(10), ['ResponseContentDisposition' => 'attachment; filename="'.$transfer->file_name.'"'])
            : route('v1.admin.content.transfers.file', ['transfer' => $transfer->id]);
    }

    /** Removes the file, its parts and an unfinished S3 upload. */
    public function delete(ContentTransfer $transfer): void
    {
        if ($transfer->path === null) {
            return;
        }
        if ($this->usesS3() && $transfer->upload_id !== null && $transfer->status === ContentTransfer::UPLOADING) {
            [$client, $bucket] = $this->s3();
            rescue(fn () => $client->abortMultipartUpload(['Bucket' => $bucket, 'Key' => $transfer->path, 'UploadId' => $transfer->upload_id]), report: false);
        }
        $this->disk()->deleteDirectory(dirname($transfer->path));
    }

    private static function partPath(ContentTransfer $transfer, int $number): string
    {
        return dirname($transfer->path)."/parts/{$number}";
    }

    /** @return array{0: S3Client, 1: string} */
    private function s3(): array
    {
        /** @var AwsS3V3Adapter $disk */
        $disk = $this->disk();

        return [$disk->getClient(), (string) $disk->getConfig()['bucket']];
    }
}
