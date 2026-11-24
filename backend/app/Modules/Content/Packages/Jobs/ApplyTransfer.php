<?php

namespace App\Modules\Content\Packages\Jobs;

use App\Models\ContentTransfer;
use App\Modules\Content\Packages\ImportPackage;
use App\Modules\Content\Packages\PackageReader;
use App\Modules\Content\Packages\TransferStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Writes an approved package into drafts (D67). The transfer row is locked and must still be
 * `applying`, and it becomes `applied` in the same transaction as the drafts, so a repeated job
 * never writes twice and a failed one writes nothing.
 */
final class ApplyTransfer extends TransferJob
{
    protected function run(ContentTransfer $transfer, string $dir): void
    {
        if ($transfer->status !== ContentTransfer::APPLYING) {
            return;
        }
        $storage = app(TransferStorage::class);
        if (! $storage->exists($transfer)) {
            $transfer->update(['status' => ContentTransfer::FAILED, 'error' => 'The uploaded file is no longer there (it expired). Upload the package again.']);

            return;
        }

        try {
            $package = app(PackageReader::class)->read($storage->download($transfer, $dir), $transfer->file_name, "{$dir}/unpacked");
            DB::transaction(function () use ($transfer, $package) {
                $locked = ContentTransfer::query()->whereKey($transfer->id)->lockForUpdate()->first();
                if ($locked?->status !== ContentTransfer::APPLYING) {
                    return;
                }
                $locks = $locked->report['locks'] ?? [];
                $plan = app(ImportPackage::class)->handle($locked->user, $package['envelope'], $package['root'], $locks);
                $locked->update(['status' => ContentTransfer::APPLIED, 'error' => null, 'report' => [...$plan->toArray(), 'locks' => $locks]]);
            });
        } catch (ValidationException $e) {
            $first = (string) collect($e->errors())->flatten()->first();
            $transfer->update(['status' => ContentTransfer::FAILED, 'error' => 'The package is no longer valid against the current content: '.Str::limit($first, 300).' Validate it again to see every problem.']);
        } catch (ConflictHttpException $e) {
            $transfer->update(['status' => ContentTransfer::FAILED, 'error' => $e->getMessage()]);
        }
    }
}
