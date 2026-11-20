<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public const SORTS = ['name', '-name', 'created_at', '-created_at', 'last_login_at', '-last_login_at'];

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array'],
            'filter.role' => ['nullable', Rule::in(['learner', 'admin'])],
            'filter.status' => ['nullable', Rule::in(['active', 'suspended'])],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
