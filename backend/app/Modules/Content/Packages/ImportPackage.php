<?php

namespace App\Modules\Content\Packages;

use App\Models\Lesson;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Blog\Actions\PostLifecycle;
use App\Modules\Content\Actions\LessonLifecycle;
use App\Modules\Content\Documents\DocumentDrafts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Applies a content package (D67): plans it again inside one transaction, so it is checked against
 * the documents as they are now, and writes every document's draft or nothing. New lessons and
 * posts are created as drafts the way the admin console creates them. Nothing is published.
 */
final class ImportPackage
{
    public function __construct(
        private readonly PackagePlanner $planner,
        private readonly LessonLifecycle $lessons,
        private readonly PostLifecycle $posts,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, int>|null  $expectedLocks  "type:slug" => lock_version from the preview the admin approved
     *
     * @throws ValidationException when the package is invalid now
     * @throws ConflictHttpException when a document changed since the preview
     */
    public function handle(User $actor, array $envelope, ?string $root, ?array $expectedLocks = null): PackagePlan
    {
        return DB::transaction(function () use ($actor, $envelope, $root, $expectedLocks) {
            $plan = $this->planner->plan($envelope, $root);
            if (! $plan->valid()) {
                throw ValidationException::withMessages($plan->errors);
            }
            foreach ($plan->documents as $doc) {
                $expected = $expectedLocks["{$doc['type']}:{$doc['slug']}"] ?? null;
                if ($expectedLocks !== null && $expected !== $doc['lock_version']) {
                    throw new ConflictHttpException(__('":slug" changed since this preview. Validate the package again to see the current changes.', ['slug' => $doc['slug']]));
                }
            }

            $plan->media->store();
            foreach ($plan->documents as $i => $doc) {
                $model = $doc['model'];
                if ($doc['is_new']) {
                    $model = $model instanceof Lesson
                        ? $this->lessons->create($actor, $model->chapter, $doc['snapshot']['title'], $doc['slug'])
                        : $this->posts->create($actor, $doc['snapshot']['title'], $doc['slug']);
                    $plan->documents[$i]['id'] = $model->id; // so the result can link to the new draft
                }
                $draft = DocumentDrafts::locked($model);
                if (! $doc['is_new']) {
                    DocumentDrafts::assertVersion($model, $draft, $doc['lock_version']);
                }
                DocumentDrafts::store($model, $draft, $doc['snapshot'], $actor);

                $counts = array_count_values(array_column($doc['changes'], 'change'));
                $this->audit->record($actor, "{$doc['type']}.package_imported", $doc['type'], $model->getKey(), [
                    'kind' => $plan->kind,
                    'generator' => $plan->generator,
                    'created' => $doc['is_new'],
                    'sections' => ['added' => $counts['added'] ?? 0, 'replaced' => $counts['replaced'] ?? 0, 'removed' => $counts['removed'] ?? 0],
                ]);
            }

            return $plan;
        });
    }
}
