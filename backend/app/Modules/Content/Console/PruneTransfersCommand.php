<?php

namespace App\Modules\Content\Console;

use App\Models\ContentTransfer;
use App\Modules\Content\Packages\PackageReader;
use App\Modules\Content\Packages\TransferStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Removes expired content package uploads and exports (files and rows, D70) and temporary
 * folders a crashed step left behind on this container. Runs hourly from the scheduler.
 */
final class PruneTransfersCommand extends Command
{
    protected $signature = 'content:prune-transfers';

    protected $description = 'Remove expired content package transfers and stale temporary folders';

    public function handle(TransferStorage $storage): int
    {
        $removed = 0;
        ContentTransfer::query()->where('expires_at', '<', now())->chunkById(100, function ($transfers) use ($storage, &$removed) {
            foreach ($transfers as $transfer) {
                $storage->delete($transfer);
                $transfer->delete();
                $removed++;
            }
        });

        // A step deletes its own folder; anything older than the longest step (15 minutes) was left by a crash.
        $stale = 0;
        foreach (is_dir(PackageReader::workDir()) ? File::directories(PackageReader::workDir()) : [] as $dir) {
            if (filemtime($dir) < now()->subHour()->getTimestamp()) {
                File::deleteDirectory($dir);
                $stale++;
            }
        }

        $this->info("Removed {$removed} expired transfers and {$stale} stale folders.");

        return self::SUCCESS;
    }
}
