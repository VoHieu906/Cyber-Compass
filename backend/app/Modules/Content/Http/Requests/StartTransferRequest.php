<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** An upload of a content package: its name and size; the bytes follow in parts. */
class StartTransferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:200', 'regex:/\.(json|zip)$/i'],
            'size' => ['required', 'integer', 'min:1', 'max:'.config('cybercompass.content_packages.max_bytes')],
        ];
    }

    public function messages(): array
    {
        return [
            'file_name.regex' => __('Upload a .ccpkg.json document or a .ccpkg.zip bundle.'),
            'size.max' => __('The package is larger than :max MB.', ['max' => intdiv(config('cybercompass.content_packages.max_bytes'), 1024 * 1024)]),
        ];
    }
}
