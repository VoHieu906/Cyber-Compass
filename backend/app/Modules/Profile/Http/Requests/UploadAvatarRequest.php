<?php

namespace App\Modules\Profile\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UploadAvatarRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // SVG is excluded on purpose: it can carry script. The image is re-encoded server-side anyway.
            'avatar' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max((int) config('cybercompass.avatar.max_kb'))
                    ->dimensions(Rule::dimensions()->minWidth(64)->minHeight(64)->maxWidth(6000)->maxHeight(6000)),
            ],
        ];
    }
}
