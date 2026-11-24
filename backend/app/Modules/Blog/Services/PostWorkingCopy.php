<?php

namespace App\Modules\Blog\Services;

use App\Models\Post;
use App\Modules\Content\Documents\BlockDocument;
use App\Modules\Content\Documents\ReadingTime;
use App\Modules\Content\Documents\WorkingCopy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The post an admin is editing. Snapshot shape:
 *
 *   { slug, title, summary, cover (media uuid|null), tags[], estimated_minutes, sections[{uuid, type, heading, payload}] }
 *
 * `estimated_minutes` is never typed: it is computed from the sections on every validation (D55).
 */
final class PostWorkingCopy extends WorkingCopy
{
    public const MAX_TAGS = 8;

    /** @param  Post  $doc */
    public function fromLive(BlockDocument $doc): array
    {
        return [
            'slug' => $doc->slug,
            'title' => $doc->title,
            'summary' => $doc->summary,
            'cover' => $doc->cover,
            'tags' => $doc->tags ?? [],
            'estimated_minutes' => $doc->estimated_minutes,
            'sections' => $this->liveSections($doc),
        ];
    }

    /** @param  Post  $doc */
    public function validate(BlockDocument $doc, array $input): array
    {
        $slugEditable = $doc->published_at === null;

        $validator = Validator::make($input, [
            'slug' => $slugEditable
                ? ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('posts', 'slug')->ignore($doc->id)]
                : ['nullable', 'string'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:300'],
            'cover' => ['nullable', 'uuid'],
            'tags' => ['present', 'array', 'max:'.self::MAX_TAGS],
            'tags.*' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'distinct'],
            ...$this->sectionRules($doc),
        ], [
            'slug.regex' => __('Use lowercase letters, numbers and single hyphens.'),
            'tags.*.regex' => __('Use lowercase letters, numbers and single hyphens.'),
        ]);

        $errors = $validator->errors()->toArray();
        [$sectionErrors, $sections] = $this->validateSections($doc, $input);
        $errors += $sectionErrors;
        $cover = is_string($input['cover'] ?? null) && $input['cover'] !== '' ? strtolower($input['cover']) : null;
        if ($cover !== null && ! isset($errors['cover'])) {
            $errors += $this->missingMedia(['cover' => $cover]);
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'slug' => $slugEditable ? (string) $input['slug'] : $doc->slug,
            'title' => trim((string) $input['title']),
            'summary' => trim((string) ($input['summary'] ?? '')),
            'cover' => $cover,
            'tags' => array_values(array_map(fn ($t) => (string) $t, $input['tags'])),
            'estimated_minutes' => ReadingTime::minutes($sections),
            'sections' => $sections,
        ];
    }
}
