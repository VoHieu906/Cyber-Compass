<?php

namespace App\Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tag' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
