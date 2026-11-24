<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The end of an upload. On S3 the client sends the ETag each part PUT returned. */
class CompleteTransferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'parts' => ['sometimes', 'array', 'max:10000'],
            'parts.*.number' => ['required', 'integer', 'min:1', 'max:10000'],
            'parts.*.etag' => ['required', 'string', 'max:200'],
        ];
    }
}
