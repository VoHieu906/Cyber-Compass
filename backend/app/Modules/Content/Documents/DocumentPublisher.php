<?php

namespace App\Modules\Content\Documents;

use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Content\Services\SectionWriter;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The shared publish and restore transactions of a block document (D55). Publishing makes the
 * working copy live in one transaction: the document row (through the type's $apply), sections
 * upserted by UUID, revision n+1, draft dropped, one audit entry. The draft is validated again,
 * so a schema tightened since it was saved still applies.
 */
final class DocumentPublisher
{
    public function __construct(
        private readonly SectionWriter $sections,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  Closure(array<string, mixed>): void  $apply  writes the snapshot's metadata to the document and saves it
     * @param  Closure(): void|null  $after  runs inside the transaction after the revision is written
     */
    public function publish(User $actor, BlockDocument&Model $doc, WorkingCopy $copy, int $lockVersion, ?string $note, Closure $apply, ?Closure $after = null): Model
    {
        return DB::transaction(function () use ($actor, $doc, $copy, $lockVersion, $note, $apply, $after) {
            $draft = DocumentDrafts::locked($doc);
            DocumentDrafts::assertVersion($doc, $draft, $lockVersion);

            $snapshot = $copy->validate($doc, WorkingCopy::toInput($copy->current($doc)));
            $copy->assertPublishable($snapshot);

            $apply($snapshot);

            $this->sections->write($doc, $snapshot['sections']);

            $revisions = $doc->revisions();
            $version = (int) $revisions->getRelated()->newQuery()
                ->where($revisions->getForeignKeyName(), $doc->getKey())
                ->lockForUpdate()->max('version') + 1;
            $revision = $revisions->create([
                'version' => $version,
                'snapshot' => $snapshot,
                'note' => $note,
                'created_by' => $actor->id,
            ]);

            $draft?->delete();
            $doc->setRelation('draft', null);

            $type = $doc->documentType();
            $this->audit->record($actor, "{$type}.published", $type, $doc->getKey(), array_filter([
                'version' => $version,
                'note' => $note,
            ], fn ($v) => $v !== null));

            $after && $after();

            return $revision;
        });
    }

    /** Copies a revision into the draft. Readers see nothing until it is published. */
    public function restore(User $actor, BlockDocument&Model $doc, WorkingCopy $copy, Model $revision, int $lockVersion): void
    {
        DB::transaction(function () use ($actor, $doc, $copy, $revision, $lockVersion) {
            $draft = DocumentDrafts::locked($doc);
            DocumentDrafts::assertVersion($doc, $draft, $lockVersion);

            $snapshot = $copy->validate($doc, WorkingCopy::toInput($revision->snapshot));
            DocumentDrafts::store($doc, $draft, $snapshot, $actor);

            $type = $doc->documentType();
            $this->audit->record($actor, "{$type}.revision_restored", $type, $doc->getKey(), ['version' => $revision->version]);
        });
    }

    /** Drops unpublished changes (the caller checks there is a live version to fall back to). */
    public function discardDraft(User $actor, BlockDocument&Model $doc): void
    {
        DB::transaction(function () use ($actor, $doc) {
            $draft = DocumentDrafts::locked($doc);
            if (! $draft) {
                return;
            }
            $draft->delete();
            $doc->setRelation('draft', null);
            $type = $doc->documentType();
            $this->audit->record($actor, "{$type}.draft_discarded", $type, $doc->getKey());
        });
    }
}
