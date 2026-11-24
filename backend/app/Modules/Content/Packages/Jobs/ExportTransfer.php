<?php

namespace App\Modules\Content\Packages\Jobs;

use App\Models\ContentTransfer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Post;
use App\Modules\Content\Packages\PackageExporter;
use App\Modules\Content\Packages\PackageZip;
use App\Modules\Content\Packages\TransferStorage;
use Illuminate\Support\Facades\File;

/** Builds a .ccpkg.zip with its images (a lesson, a post or a whole course) and stores it for download. */
final class ExportTransfer extends TransferJob
{
    protected function run(ContentTransfer $transfer, string $dir): void
    {
        if ($transfer->status !== ContentTransfer::EXPORTING) {
            return;
        }
        $exporter = app(PackageExporter::class);
        $subject = $transfer->subject;
        $envelope = match ($subject['type']) {
            'lesson' => $exporter->lesson(Lesson::query()->findOrFail($subject['id']), $subject['sections'] ?? null),
            'post' => $exporter->post(Post::query()->findOrFail($subject['id']), $subject['sections'] ?? null),
            'course' => $exporter->course(Course::query()->findOrFail($subject['id'])),
        };

        File::ensureDirectoryExists($dir, 0700);
        $file = "{$dir}/{$transfer->file_name}";
        $summary = app(PackageZip::class)->write($envelope, $file);
        app(TransferStorage::class)->put($transfer, $file);

        $transfer->update(['status' => ContentTransfer::READY, 'size_bytes' => filesize($file), 'report' => $summary, 'error' => null]);
    }
}
