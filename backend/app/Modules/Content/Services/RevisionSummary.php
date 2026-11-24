<?php

namespace App\Modules\Content\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * What each published version changed compared with the one before it: which metadata
 * fields, and how many sections were added, removed or edited, and whether they were
 * reordered. Computed on read from the stored snapshots; nothing extra is stored.
 * Works for any block document (D55): the fields are the snapshot's keys other than sections.
 */
final class RevisionSummary
{
    /**
     * Summaries keyed by version.
     *
     * @param  Collection<int, Model>  $revisions  any order
     * @return array<int, array{initial: bool, fields: list<string>, sections: array{added: int, removed: int, edited: int, reordered: bool}}>
     */
    public function forAll(Collection $revisions): array
    {
        $ordered = $revisions->sortBy('version')->values();
        $summaries = [];
        $previous = null;

        foreach ($ordered as $revision) {
            $summaries[$revision->version] = $this->compare($previous?->snapshot, $revision->snapshot);
            $previous = $revision;
        }

        return $summaries;
    }

    /** @return array{initial: bool, fields: list<string>, sections: array{added: int, removed: int, edited: int, reordered: bool}} */
    public function compare(?array $before, array $after): array
    {
        if ($before === null) {
            return ['initial' => true, 'fields' => [], 'sections' => ['added' => count($after['sections'] ?? []), 'removed' => 0, 'edited' => 0, 'reordered' => false]];
        }

        $keys = array_diff(array_unique([...array_keys($after), ...array_keys($before)]), ['sections']);
        $fields = array_values(array_filter($keys, fn ($f) => ($before[$f] ?? null) !== ($after[$f] ?? null)));

        $old = collect($before['sections'] ?? [])->keyBy('uuid');
        $new = collect($after['sections'] ?? [])->keyBy('uuid');
        $kept = $new->keys()->intersect($old->keys());

        return [
            'initial' => false,
            'fields' => $fields,
            'sections' => [
                'added' => $new->keys()->diff($old->keys())->count(),
                'removed' => $old->keys()->diff($new->keys())->count(),
                // Snapshots from before D68 have no `sources` key; that alone is not an edit.
                'edited' => $kept->filter(fn ($uuid) => $old[$uuid] + ['sources' => null] != $new[$uuid] + ['sources' => null])->count(),
                'reordered' => $old->keys()->intersect($kept)->values()->all() !== $new->keys()->intersect($kept)->values()->all(),
            ],
        ];
    }
}
