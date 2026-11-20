<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChapterRequest extends FormRequest
{
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:160']];
    }
}
