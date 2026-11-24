<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A zip export with images: a lesson, a post (whole or some sections) or a whole course. */
class StartExportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['lesson', 'post', 'course'])],
            'id' => ['required', 'integer', 'min:1'],
            'sections' => ['nullable', 'array', 'max:150', Rule::prohibitedIf($this->input('type') === 'course')],
            'sections.*' => ['string', 'max:80'],
        ];
    }
}
