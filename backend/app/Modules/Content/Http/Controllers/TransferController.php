<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContentTransfer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Post;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Content\Http\Requests\CompleteTransferRequest;
use App\Modules\Content\Http\Requests\StartExportRequest;
use App\Modules\Content\Http\Requests\StartTransferRequest;
use App\Modules\Content\Http\Resources\TransferResource;
use App\Modules\Content\Packages\Jobs\ApplyTransfer;
use App\Modules\Content\Packages\Jobs\ExportTransfer;
use App\Modules\Content\Packages\Jobs\ValidateTransfer;
use App\Modules\Content\Packages\TransferStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Content package uploads and exports (F6, D70). An upload is started, sent in parts (to S3 by
 * presigned URL in production, to this API locally), completed, validated on the worker into a
 * preview, and applied on the worker into drafts. Each admin sees only their own transfers.
 */
final class TransferController extends Controller
{
    public function __construct(private readonly TransferStorage $storage) {}

    /** Start an upload: returns the transfer and how to send its parts. */
    public function store(StartTransferRequest $request): JsonResponse
    {
        Gate::authorize('create', ContentTransfer::class);

        $transfer = ContentTransfer::query()->create([
            'user_id' => $request->user()->id,
            'direction' => 'import',
            'status' => ContentTransfer::UPLOADING,
            'file_name' => basename($request->string('file_name')->toString()),
            'size_bytes' => $request->integer('size'),
            'expires_at' => now()->addDays(config('cybercompass.content_packages.ttl_days')),
        ]);
        $transfer->path = "imports/{$transfer->id}/package";
        $resource = new TransferResource($transfer);
        $resource->upload = $this->storage->start($transfer);

        return $resource->response()->setStatusCode(201);
    }

    /** One part of a local upload (development); on S3 the browser sends parts to S3 itself. */
    public function part(Request $request, ContentTransfer $transfer, int $part): JsonResponse
    {
        Gate::authorize('upload', $transfer);
        if ($this->storage->usesS3()) {
            throw new NotFoundHttpException;
        }
        if ($transfer->status !== ContentTransfer::UPLOADING || $part < 1 || $part > $transfer->parts) {
            throw new ConflictHttpException(__('This upload does not take that part.'));
        }
        $expected = $part < $transfer->parts ? TransferStorage::partBytes() : $transfer->size_bytes - ($transfer->parts - 1) * TransferStorage::partBytes();
        $body = $request->getContent();
        if (strlen($body) !== $expected) {
            throw ValidationException::withMessages(['part' => [__('Part :n should be :bytes bytes.', ['n' => $part, 'bytes' => $expected])]]);
        }
        $this->storage->putPart($transfer, $part, $body);

        return response()->json(['data' => ['part' => $part]]);
    }

    /** Finish the upload and queue the validation. */
    public function complete(CompleteTransferRequest $request, ContentTransfer $transfer): TransferResource
    {
        Gate::authorize('upload', $transfer);
        if ($transfer->status !== ContentTransfer::UPLOADING) {
            throw new ConflictHttpException(__('This upload is already complete.'));
        }
        try {
            $this->storage->complete($transfer, $request->input('parts', []));
        } catch (ValidationException $e) {
            $transfer->update(['status' => ContentTransfer::FAILED, 'error' => collect($e->errors())->flatten()->first()]);
            throw $e;
        }
        $transfer->update(['status' => ContentTransfer::VALIDATING, 'upload_id' => null]);
        ValidateTransfer::dispatch($transfer->id);

        return new TransferResource($transfer->fresh());
    }

    public function show(ContentTransfer $transfer): TransferResource
    {
        Gate::authorize('view', $transfer);

        return new TransferResource($transfer);
    }

    /** Validate again (after the content changed, or a failed apply). */
    public function validatePackage(ContentTransfer $transfer): TransferResource
    {
        Gate::authorize('apply', $transfer);
        DB::transaction(function () use ($transfer) {
            $locked = ContentTransfer::query()->whereKey($transfer->id)->lockForUpdate()->first();
            if ($locked->direction !== 'import' || ! in_array($locked->status, [ContentTransfer::READY, ContentTransfer::FAILED], true)) {
                throw new ConflictHttpException(__('This package cannot be validated now.'));
            }
            $this->assertFile($locked);
            $locked->update(['status' => ContentTransfer::VALIDATING, 'error' => null]);
            ValidateTransfer::dispatch($locked->id);
        });

        return new TransferResource($transfer->fresh());
    }

    /**
     * Apply the previewed package to drafts, on the worker. Idempotent: a second call while it is
     * being applied, or after, starts nothing and returns the transfer as it is.
     */
    public function apply(ContentTransfer $transfer): JsonResponse
    {
        Gate::authorize('apply', $transfer);
        DB::transaction(function () use ($transfer) {
            $locked = ContentTransfer::query()->whereKey($transfer->id)->lockForUpdate()->first();
            if (in_array($locked->status, [ContentTransfer::APPLYING, ContentTransfer::APPLIED], true)) {
                return;
            }
            if ($locked->direction !== 'import' || $locked->status !== ContentTransfer::READY) {
                throw new ConflictHttpException(__('This package is not ready to apply.'));
            }
            if (! ($locked->report['valid'] ?? false)) {
                throw ValidationException::withMessages(['package' => [__('The package has errors. Fix them and upload it again.')]]);
            }
            $this->assertFile($locked);
            $locked->update(['status' => ContentTransfer::APPLYING]);
            ApplyTransfer::dispatch($locked->id);
        });

        return (new TransferResource($transfer->fresh()))->response()->setStatusCode(202);
    }

    public function destroy(ContentTransfer $transfer): JsonResponse
    {
        Gate::authorize('delete', $transfer);
        if (in_array($transfer->status, [ContentTransfer::APPLYING, ContentTransfer::VALIDATING, ContentTransfer::EXPORTING], true)) {
            throw new ConflictHttpException(__('Wait until the package has been processed.'));
        }
        $this->storage->delete($transfer);
        $transfer->delete();

        return response()->json(null, 204);
    }

    /** Start a zip export with images; download it once it is ready. */
    public function export(StartExportRequest $request, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('create', ContentTransfer::class);
        $type = $request->string('type')->toString();
        $model = match ($type) {
            'lesson' => Lesson::query()->findOrFail($request->integer('id')),
            'post' => Post::query()->findOrFail($request->integer('id')),
            'course' => Course::query()->findOrFail($request->integer('id')),
        };
        $sections = $request->input('sections');

        $transfer = DB::transaction(function () use ($request, $audit, $type, $model, $sections) {
            $transfer = ContentTransfer::query()->create([
                'user_id' => $request->user()->id,
                'direction' => 'export',
                'status' => ContentTransfer::EXPORTING,
                'file_name' => $model->slug.($sections ? '.sections' : '').'.ccpkg.zip',
                'subject' => ['type' => $type, 'id' => $model->id, 'sections' => $sections],
                'expires_at' => now()->addDays(config('cybercompass.content_packages.ttl_days')),
            ]);
            $transfer->update(['path' => "exports/{$transfer->id}/{$transfer->file_name}"]);
            $audit->record($request->user(), "{$type}.package_exported", $type, $model->id, ['format' => 'zip', 'sections' => $sections]);
            ExportTransfer::dispatch($transfer->id);

            return $transfer;
        });

        return (new TransferResource($transfer->fresh()))->response()->setStatusCode(202);
    }

    /** A short-lived link to a finished export. */
    public function download(ContentTransfer $transfer): JsonResponse
    {
        Gate::authorize('view', $transfer);
        if ($transfer->direction !== 'export' || $transfer->status !== ContentTransfer::READY) {
            throw new ConflictHttpException(__('This export is not ready.'));
        }
        $this->assertFile($transfer);

        return response()->json(['data' => ['url' => $this->storage->downloadUrl($transfer)]]);
    }

    /** The export file itself, when the transfers disk is local (S3 serves presigned links instead). */
    public function file(ContentTransfer $transfer): StreamedResponse
    {
        Gate::authorize('view', $transfer);
        if ($this->storage->usesS3() || $transfer->direction !== 'export' || $transfer->status !== ContentTransfer::READY) {
            throw new NotFoundHttpException;
        }
        $this->assertFile($transfer);

        return $this->storage->disk()->download($transfer->path, $transfer->file_name);
    }

    private function assertFile(ContentTransfer $transfer): void
    {
        if ($transfer->expired() || ! $this->storage->exists($transfer)) {
            throw new GoneHttpException(__('This file expired and was removed. Upload or export the package again.'));
        }
    }
}
