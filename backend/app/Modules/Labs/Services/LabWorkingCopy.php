<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;
use Illuminate\Validation\ValidationException;

/**
 * What an admin edits (D74): the draft when there is one, otherwise the live definition.
 * Validation is LabDefinitionValidator's; this class only chooses the source and turns errors
 * into a 422 keyed by path, the way the lesson editor's errors are.
 */
final class LabWorkingCopy
{
    public function __construct(private readonly LabDefinitionValidator $validator) {}

    /** @return array<string, mixed> */
    public function current(Lab $lab): array
    {
        $lab->loadMissing('draft');

        return $lab->draft ? LabDefinition::normalize($lab->draft->snapshot) : LabDefinition::fromLab($lab);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> the normalized definition
     *
     * @throws ValidationException
     */
    public function validate(array $input, bool $replay = false): array
    {
        $def = LabDefinition::normalize(array_diff_key($input, ['lock_version' => 1, 'note' => 1, 'confirm_restart' => 1]));
        $report = $this->validator->check($def, $replay);
        if ($report['errors']) {
            throw ValidationException::withMessages($report['errors']);
        }

        return $def;
    }

    /**
     * Errors and warnings without throwing, for the editor's "check" button.
     *
     * @param  array<string, mixed>  $def
     * @return array{errors: array<string, list<string>>, warnings: array<string, list<string>>}
     */
    public function report(array $def, bool $replay): array
    {
        return $this->validator->check(LabDefinition::normalize($def), $replay);
    }
}
