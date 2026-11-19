<?php

namespace App\Modules\Profile\Http\Requests;

use App\Enums\EducationLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // International format: optional +, digits, spaces, dashes, parentheses.
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-().]{6,32}$/'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'phone'] as $field) {
            if (is_string($this->{$field})) {
                $trimmed = trim($this->{$field});
                $this->merge([$field => $trimmed === '' && $field !== 'name' ? null : $trimmed]);
            }
        }
    }
}
