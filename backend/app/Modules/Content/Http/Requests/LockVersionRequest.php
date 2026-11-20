<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Any change to a lesson's working copy carries the lock_version the client loaded
 * (0 when there was no draft). The body of a draft save is validated by
 * Content\Services\LessonWorkingCopy, the same rules the importer uses.
 */
class LockVersionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }
}
