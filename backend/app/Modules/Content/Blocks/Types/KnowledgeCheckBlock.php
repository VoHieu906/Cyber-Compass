<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

/**
 * Ungraded questions inside a lesson. The answers ship with the lesson because nothing depends
 * on them: they never affect progress or completion (PHASE_3_5 A4). Graded exercises are Phase 5.
 */
final class KnowledgeCheckBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'knowledge-check';
    }

    public function label(): string
    {
        return 'Knowledge check';
    }

    public function description(): string
    {
        return 'One to three practice questions with explanations. Ungraded; never affects progress.';
    }

    public function validate(array $payload): array
    {
        $errors = parent::validate($payload);
        if ($errors !== []) {
            return $errors;
        }

        foreach ($payload['questions'] as $i => $question) {
            if (! in_array(true, array_column($question['options'], 'correct'), true)) {
                $errors["questions.{$i}.options"][] = __('Mark at least one option as correct.');
            }
        }

        return $errors;
    }
}
