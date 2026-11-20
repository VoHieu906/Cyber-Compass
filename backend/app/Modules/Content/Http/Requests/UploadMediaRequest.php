<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UploadMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Raster only. SVG can carry script and is never accepted or served (prompt rule).
            'image' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5120)
                    ->dimensions(Rule::dimensions()->minWidth(64)->minHeight(64)->maxWidth(6000)->maxHeight(6000)),
            ],
            // Required: every lesson image must be described for screen reader users.
            'alt' => ['required', 'string', 'min:3', 'max:250'],
        ];
    }
}
