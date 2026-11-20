<?php

namespace App\Modules\Content\Blocks\Types;

use App\Modules\Content\Blocks\JsonSchemaBlock;

final class ProseBlock extends JsonSchemaBlock
{
    public function key(): string
    {
        return 'prose';
    }

    public function label(): string
    {
        return 'Text';
    }

    public function description(): string
    {
        return 'Paragraphs, bullet and numbered lists, **bold**, `code` and [links](https://example.com).';
    }

    /** Inline links: [text](url). Only https links render as links, so anything else is refused here. */
    public const LINK = '/\[([^\]\n]+)\]\(([^)\s]*)\)/';

    public function validate(array $payload): array
    {
        $errors = parent::validate($payload);
        if ($errors !== []) {
            return $errors;
        }

        preg_match_all(self::LINK, $payload['text'], $links, PREG_SET_ORDER);
        foreach ($links as [, , $url]) {
            if (! self::isHttps($url)) {
                $errors['text'][] = __('Links must start with https:// (found ":url").', ['url' => mb_strimwidth($url, 0, 60, '...')]);
            }
        }

        return $errors;
    }

    private static function isHttps(string $url): bool
    {
        return str_starts_with($url, 'https://')
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_string(parse_url($url, PHP_URL_HOST));
    }

    public function normalize(array $payload): array
    {
        return is_string($payload['text'] ?? null) ? ['text' => trim($payload['text'])] + $payload : $payload;
    }
}
