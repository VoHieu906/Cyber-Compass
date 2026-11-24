<?php

namespace App\Modules\Content\Documents;

use App\Models\LessonMedia;
use App\Modules\Content\Blocks\BlockRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The block document an admin is editing: the draft if one exists, otherwise the live content.
 * Drafts and revisions store the same snapshot: the type's metadata plus
 * `sections[{uuid, type, heading, payload}]`. Subclasses own the metadata rules
 * (LessonWorkingCopy, PostWorkingCopy); the section rules live here, once (D17, D18, D55).
 */
abstract class WorkingCopy
{
    /** @var array<string, true> images a content package brings, stored only when it is applied (F6) */
    private array $assumedMedia = [];

    public function __construct(protected readonly BlockRegistry $blocks) {}

    /**
     * A copy that treats these media UUIDs as existing, so a package's images (not stored before
     * it is applied) pass the image check. Everything else is validated as usual.
     *
     * @param  list<string>  $uuids
     */
    public function assumingMedia(array $uuids): static
    {
        $copy = clone $this;
        $copy->assumedMedia = array_fill_keys($uuids, true);

        return $copy;
    }

    /** @return array<string, mixed> */
    abstract public function fromLive(BlockDocument $doc): array;

    /**
     * Validates input (request body or a revision snapshot) into a snapshot.
     * Error keys match the request body: `title`, `sections.3.payload.items.0`.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    abstract public function validate(BlockDocument $doc, array $input): array;

    /**
     * What a document needs before readers can see it, beyond what a draft needs.
     *
     * @param  array<string, mixed>  $snapshot
     *
     * @throws ValidationException
     */
    public function assertPublishable(array $snapshot): void
    {
        $errors = [];
        if (trim((string) ($snapshot['summary'] ?? '')) === '') {
            $errors['summary'] = [__('Write a summary before publishing.')];
        }
        if (($snapshot['sections'] ?? []) === []) {
            $errors['sections'] = [__('Add at least one section before publishing.')];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    public function current(BlockDocument $doc): array
    {
        return $doc->draft?->snapshot ?? $this->fromLive($doc);
    }

    /** The version a client must send to change the working copy: 0 while there is no draft. */
    public function lockVersion(BlockDocument $doc): int
    {
        return $doc->draft?->lock_version ?? 0;
    }

    /**
     * Section uuids this document may use: its live sections, its draft and its revisions.
     * Any other id in a request is rejected, so a section can never be moved out of another document.
     *
     * @return list<string>
     */
    public function knownUuids(BlockDocument $doc): array
    {
        $uuids = $doc->sections()->pluck('uuid')->all();
        foreach ([$doc->draft?->snapshot, ...$doc->revisions()->pluck('snapshot')->all()] as $snapshot) {
            foreach ($snapshot['sections'] ?? [] as $section) {
                $uuids[] = $section['uuid'];
            }
        }

        return array_values(array_unique($uuids));
    }

    /**
     * Snapshot -> the request shape validate() takes (sections carry `id`, not `uuid`).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function toInput(array $snapshot): array
    {
        return [
            ...$snapshot,
            'sections' => array_map(
                fn (array $s) => ['id' => $s['uuid'], 'type' => $s['type'], 'heading' => $s['heading'], 'payload' => $s['payload'], 'sources' => $s['sources'] ?? null],
                $snapshot['sections'] ?? [],
            ),
        ];
    }

    /** @return list<array{uuid: string, type: string, heading: ?string, payload: array}> */
    protected function liveSections(BlockDocument $doc): array
    {
        return $doc->sections()->get()
            ->map(fn (Model $s) => ['uuid' => $s->uuid, 'type' => $s->type, 'heading' => $s->heading, 'payload' => $s->payload, 'sources' => $s->sources])
            ->all();
    }

    /** Laravel rules for the `sections` list itself; blocks are checked by validateSections(). */
    protected function sectionRules(BlockDocument $doc): array
    {
        return [
            'sections' => ['present', 'array', 'max:'.$doc->maxSections()],
            'sections.*' => ['array'],
            'sections.*.id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Validates the blocks, their ids and their images. Sections without an `id` get a new UUID.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, list<string>>, 1: list<array{uuid: string, type: string, heading: ?string, payload: array}>} [errors, sections]
     */
    protected function validateSections(BlockDocument $doc, array $input): array
    {
        $errors = [];
        $sections = array_values(is_array($input['sections'] ?? null) ? $input['sections'] : []);
        $clean = [];
        try {
            $clean = $this->blocks->validateSections($sections);
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }

        $errors += $this->checkIds($doc, $sections);
        $errors += $this->checkMedia($clean);

        $withIds = array_map(fn (array $s, int $i) => [
            'uuid' => is_string($sections[$i]['id'] ?? null) ? strtolower($sections[$i]['id']) : (string) Str::uuid(),
            ...$s,
        ], $clean, array_keys($clean));

        return [$errors, $withIds];
    }

    /** @return array<string, list<string>> */
    private function checkIds(BlockDocument&Model $doc, array $sections): array
    {
        $errors = [];
        $seen = [];
        $ids = array_filter(array_map(fn ($s) => is_string($s['id'] ?? null) ? strtolower($s['id']) : null, $sections));
        if ($ids === []) {
            return [];
        }

        $known = array_flip($this->knownUuids($doc));
        // A uuid not yet in the database is a new section the client named itself: allowed,
        // as long as no other document of this type owns it.
        $relation = $doc->sections();
        $foreign = array_flip($relation->getRelated()->newQuery()
            ->whereIn('uuid', $ids)
            ->where($relation->getForeignKeyName(), '!=', $doc->getKey())
            ->pluck('uuid')->all());

        foreach ($ids as $i => $id) {
            if (isset($seen[$id])) {
                $errors["sections.{$i}.id"] = [__('Each section id can appear only once.')];
            } elseif (isset($foreign[$id]) && ! isset($known[$id])) {
                $errors["sections.{$i}.id"] = [$doc->documentType() === 'post'
                    ? __('This section belongs to another post.')
                    : __('This section belongs to another lesson.')];
            }
            $seen[$id] = true;
        }

        return $errors;
    }

    /**
     * Every image a `media` block names must exist.
     *
     * @return array<string, list<string>>
     */
    private function checkMedia(array $sections): array
    {
        $wanted = [];
        foreach ($sections as $i => $section) {
            if ($section['type'] === 'media') {
                $wanted["sections.{$i}.payload.media"] = $section['payload']['media'];
            }
        }

        return $this->missingMedia($wanted);
    }

    /**
     * @param  array<string, string>  $wanted  error key => media uuid
     * @return array<string, list<string>>
     */
    protected function missingMedia(array $wanted): array
    {
        $wanted = array_diff_key($wanted, array_filter($wanted, fn ($uuid) => isset($this->assumedMedia[$uuid])));
        if ($wanted === []) {
            return [];
        }

        $existing = array_flip(LessonMedia::query()->whereIn('uuid', array_values($wanted))->pluck('uuid')->all());
        $errors = [];
        foreach ($wanted as $key => $uuid) {
            if (! isset($existing[$uuid])) {
                $errors[$key] = [__('This image does not exist. Upload it again.')];
            }
        }

        return $errors;
    }
}
