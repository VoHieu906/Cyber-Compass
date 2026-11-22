<?php

namespace App\Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPostIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'filter.has_draft' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
