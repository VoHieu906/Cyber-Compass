<?php

namespace App\Modules\Content\Packages\Jobs;

use App\Models\ContentTransfer;
use App\Modules\Content\Packages\PackagePlanner;
use App\Modules\Content\Packages\PackageReader;
use App\Modules\Content\Packages\TransferStorage;
use Illuminate\Validation\ValidationException;

/** Reads an uploaded package and stores its preview (D67): errors, warnings and the changes. Writes no content. */
final class ValidateTransfer extends TransferJob
{
    protected function run(ContentTransfer $transfer, string $dir): void
    {
        if ($transfer->status !== ContentTransfer::VALIDATING) {
            return;
        }
        $storage = app(TransferStorage::class);
        if (! $storage->exists($transfer)) {
            $transfer->update(['status' => ContentTransfer::FAILED, 'error' => 'The uploaded file is no longer there (it expired). Upload the package again.']);

            return;
        }

        try {
            $package = app(PackageReader::class)->read($storage->download($transfer, $dir), $transfer->file_name, "{$dir}/unpacked");
            $plan = app(PackagePlanner::class)->plan($package['envelope'], $package['root']);
            $report = [...$plan->toArray(), 'locks' => self::locks($plan->documents)];
        } catch (ValidationException $e) {
            $report = ['kind' => null, 'generator' => null, 'valid' => false, 'errors' => $e->errors(), 'warnings' => [], 'documents' => [], 'locks' => []];
        }

        $transfer->update(['status' => ContentTransfer::READY, 'report' => $report, 'error' => null]);
    }

    /** @return array<string, int> "type:slug" => the lock version the preview saw */
    public static function locks(array $documents): array
    {
        $locks = [];
        foreach ($documents as $doc) {
            $locks["{$doc['type']}:{$doc['slug']}"] = $doc['lock_version'];
        }

        return $locks;
    }
}
