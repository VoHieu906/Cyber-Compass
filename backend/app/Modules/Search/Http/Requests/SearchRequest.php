<?php

namespace App\Modules\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchRequest extends FormRequest
{
    /** Trimmed before validation, so "  a " is too short rather than a match on everything. */
    protected function prepareForValidation(): void
    {
        $this->merge(['q' => trim((string) $this->query('q', ''))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:100']];
    }
}
