<?php

namespace App\Modules\Content\Documents;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The draft row of a block document: locked for the transaction, checked against the client's
 * lock_version (optimistic locking), and stored with the version bumped. Call inside a transaction.
 */
final class DocumentDrafts
{
    /** The document's draft row, locked for this transaction (null when there is none). */
    public static function locked(BlockDocument&Model $doc): ?Model
    {
        $draft = $doc->draft()->lockForUpdate()->first();
        $doc->setRelation('draft', $draft);

        return $draft;
    }

    /** @throws ConflictHttpException when someone saved since the client loaded the document */
    public static function assertVersion(BlockDocument $doc, ?Model $draft, int $lockVersion): void
    {
        if (($draft?->lock_version ?? 0) !== $lockVersion) {
            throw new ConflictHttpException($doc->documentType() === 'post'
                ? __('Someone else changed this post since you opened it. Reload to see their changes, then make yours again.')
                : __('Someone else changed this lesson since you opened it. Reload to see their changes, then make yours again.'));
        }
    }

    /** @param  array<string, mixed>  $snapshot */
    public static function store(BlockDocument&Model $doc, ?Model $draft, array $snapshot, User $actor): Model
    {
        $draft ??= $doc->draft()->make(['lock_version' => 0]);
        $draft->fill([
            'snapshot' => $snapshot,
            'lock_version' => $draft->lock_version + 1,
            'updated_by' => $actor->id,
        ])->save();
        $doc->setRelation('draft', $draft);

        return $draft;
    }
}
