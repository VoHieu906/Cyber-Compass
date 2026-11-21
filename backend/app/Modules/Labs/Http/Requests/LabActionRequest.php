<?php

namespace App\Modules\Labs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** The envelope of a lab action. The engine checks the action itself (ARCHITECTURE §7.1). */
class LabActionRequest extends FormRequest
{
    private const MAX_ACTION_BYTES = 8 * 1024;

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'array'],
            'action.type' => ['required', 'string', 'max:40'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (strlen((string) json_encode($this->input('action'))) > self::MAX_ACTION_BYTES) {
                $validator->errors()->add('action', __('This action is too large.'));
            }
        }];
    }
}
