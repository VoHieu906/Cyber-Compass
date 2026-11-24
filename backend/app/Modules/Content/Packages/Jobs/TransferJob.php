<?php

namespace App\Modules\Content\Packages\Jobs;

use App\Models\ContentTransfer;
use App\Modules\Content\Packages\PackageReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * One step of a content transfer (D70) on the worker. Each step copies the file from the transfers
 * disk into a folder of its own and deletes that folder when it ends, however it ends: a step never
 * relies on files another step or container left behind.
 *
 * One try and a 15-minute limit: a retry would only repeat the same failure, and with one try
 * nothing else would clean up, so failed() records the reason and removes the folder itself.
 */
abstract class TransferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $transferId)
    {
        $this->afterCommit();
    }

    /** The step's own work, in its folder; the folder is removed afterwards. */
    abstract protected function run(ContentTransfer $transfer, string $dir): void;

    /** The folder this step unpacks into: one per transfer and step, under the package work folder. */
    public function dir(): string
    {
        return PackageReader::workDir().'/transfer-'.$this->transferId.'-'.class_basename($this);
    }

    public function handle(): void
    {
        $transfer = ContentTransfer::query()->find($this->transferId);
        if (! $transfer) {
            return;
        }
        try {
            $this->run($transfer, $this->dir());
        } finally {
            File::deleteDirectory($this->dir());
        }
    }

    public function failed(?Throwable $e): void
    {
        File::deleteDirectory($this->dir());
        ContentTransfer::query()->whereKey($this->transferId)->update([
            'status' => ContentTransfer::FAILED,
            'error' => $e instanceof TimeoutExceededException
                ? 'Processing took longer than 15 minutes and was stopped. Split the package into smaller ones.'
                : "Processing stopped on an unexpected error (logged on the server for transfer {$this->transferId}). Try again.",
        ]);
    }
}
