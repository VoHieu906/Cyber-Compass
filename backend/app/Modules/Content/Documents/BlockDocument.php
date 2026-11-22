<?php

namespace App\Modules\Content\Documents;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A document whose body is a list of typed blocks with a draft and published revisions:
 * a lesson or a blog post (ARCHITECTURE D55). The shared pieces (WorkingCopy, SectionWriter,
 * DocumentDrafts, DocumentPublisher, BlockSerializer, RevisionSummary) work through this.
 *
 * Implementations are Eloquent models whose section, draft and revision tables are twins:
 * `<owner>_id`, sections with a unique `uuid` and unique (owner, position), drafts unique per
 * owner with `snapshot` and `lock_version`, revisions unique (owner, version).
 */
interface BlockDocument
{
    /** Live sections, ordered by position. */
    public function sections(): HasMany;

    public function draft(): HasOne;

    /** Published revisions, newest first. */
    public function revisions(): HasMany;

    /** 'lesson' or 'post': the audit entity type and action prefix. */
    public function documentType(): string;

    public function maxSections(): int;
}
