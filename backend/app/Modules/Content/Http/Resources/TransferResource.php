<?php

namespace App\Modules\Content\Http\Resources;

use App\Models\ContentTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A content package upload or export (F6). `report` is the preview of an import (errors by place in
 * the package, warnings, the section changes per document) or what an export holds.
 *
 * @mixin ContentTransfer
 */
class TransferResource extends JsonResource
{
    /** @var array<string, mixed>|null upload instructions, only when the upload starts */
    public ?array $upload = null;

    public function toArray(Request $request): array
    {
        $report = $this->report;
        unset($report['locks']);

        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'status' => $this->status,
            'file_name' => $this->file_name,
            'size_bytes' => $this->size_bytes,
            'report' => $report,
            'error' => $this->error,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            ...($this->upload !== null ? ['upload' => $this->upload] : []),
        ];
    }
}
