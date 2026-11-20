<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\ChecksReferences;
use App\Modules\Content\Blocks\JsonSchemaBlock;

/** Messages between actors, one step at a time. The TCP handshake is the reference example. */
final class SequenceBlock extends JsonSchemaBlock
{
    use ChecksReferences;

    public function key(): string
    {
        return 'sequence';
    }

    public function label(): string
    {
        return 'Sequence';
    }

    public function description(): string
    {
        return 'Messages between two to five actors, stepped through one at a time.';
    }

    public function editor(): string
    {
        return 'json';
    }

    public function validate(array $payload): array
    {
        $errors = parent::validate($payload);
        if ($errors !== []) {
            return $errors;
        }

        $actors = self::collectIds($payload['actors'], 'actors', $errors);
        foreach ($payload['messages'] as $i => $message) {
            $from = self::mustExist($message['from'], $actors, "messages.{$i}.from", $errors);
            self::mustExist($message['to'], $actors, "messages.{$i}.to", $errors);
            if ($from && $message['from'] === $message['to']) {
                $errors["messages.{$i}.to"][] = __('A message needs two different actors.');
            }
            foreach (array_keys($message['states'] ?? []) as $actor) {
                self::mustExist((string) $actor, $actors, "messages.{$i}.states.{$actor}", $errors);
            }
        }

        return $errors;
    }
}
