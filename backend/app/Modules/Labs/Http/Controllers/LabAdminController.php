<?php

namespace App\Modules\Labs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Lab;
use App\Models\LabRevision;
use App\Modules\Labs\Services\LabFiles;
use App\Modules\Labs\Services\LabLifecycle;
use App\Modules\Labs\Services\LabWorkingCopy;
use App\Modules\Labs\Services\PublishLab;
use App\Modules\Labs\Services\SimulatedLabProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Lab management for admins (F7, D73 to D78). Everything here carries answers (the scenario and the
 * solutions), so it lives only under the admin prefix and is never reused for learners.
 */
final class LabAdminController extends Controller
{
    public function __construct(private readonly SimulatedLabProvider $engines) {}

    /**
     * Labs for the admin list.
     *
     * Every lab, archived ones too, in the order learners see them, with whether it has unpublished
     * changes, whether the database owns it (D73) and how many learners started and finished it.
     * `engines` lists what a new lab can be built on.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Lab::class);
        $shadowed = $this->shadowed();
        $labs = Lab::query()->with('domain:id,slug,name')->withExists(['draft', 'revisions'])
            ->withCount(['attempts as started' => fn ($q) => $q->where('action_count', '>', 0), 'attempts as completed' => fn ($q) => $q->whereNotNull('completed_at')])
            ->orderBy('position')->get();

        return response()->json([
            'data' => $labs->map(fn (Lab $lab) => [
                'id' => $lab->id,
                'slug' => $lab->slug,
                'title' => $lab->title,
                'engine' => $lab->engine,
                'status' => $lab->status,
                'is_archived' => $lab->is_archived,
                'difficulty' => $lab->difficulty,
                'domain' => ['slug' => $lab->domain->slug, 'name' => $lab->domain->name],
                'has_draft' => $lab->draft_exists,
                'owned' => $lab->updated_by !== null || $lab->draft_exists || $lab->revisions_exists,
                // Also defined in labs.php while labs/<slug>.php exists: the old entry should go (D73).
                'shadowed' => in_array($lab->slug, $shadowed, true),
                'started' => $lab->started,
                'completed' => $lab->completed,
            ]),
            'meta' => ['domains' => Domain::query()->orderBy('name')->get(['slug', 'name']), 'engines' => array_map(fn (string $key) => ['key' => $key, 'checkpoint_kinds' => ['answer', ...$this->engines->engineFor($key)->checkpointKinds()]], $this->engines->engineKeys())],
        ]);
    }

    /**
     * Create a lab.
     *
     * Starts as an unpublished draft built from the engine's starter scenario and solutions.
     * The slug is fixed once chosen.
     */
    public function store(Request $request, LabLifecycle $lifecycle, LabWorkingCopy $copies, PublishLab $publish): JsonResponse
    {
        Gate::authorize('create', Lab::class);
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('labs', 'slug')],
            'engine' => ['required', 'string', Rule::in($this->engines->engineKeys())],
            'title' => ['required', 'string', 'max:200'],
            'domain' => ['required', 'string', Rule::exists('domains', 'slug')],
        ]);
        $lab = $lifecycle->create($request->user(), $data['slug'], $data['engine'], $data['title'], $data['domain']);

        return response()->json(['data' => $this->payload($lab->refresh(), $copies, $publish)], 201);
    }

    /** One lab for editing: the working copy (draft, else live), its lock version and what publishing would do to learners. */
    public function show(Lab $lab, LabWorkingCopy $copies, PublishLab $publish): JsonResponse
    {
        Gate::authorize('view', $lab);

        return response()->json(['data' => $this->payload($lab, $copies, $publish)]);
    }

    /**
     * Save the draft.
     *
     * Body: `lock_version` (0 when the lab has no draft) and the definition fields. 409 when someone
     * else saved first; 422 keyed by path (`scenario.checkpoints.0.path`). Saving makes the lab
     * database-owned: content files no longer overwrite it (D73).
     */
    public function saveDraft(Request $request, Lab $lab, LabLifecycle $lifecycle, LabWorkingCopy $copies, PublishLab $publish): JsonResponse
    {
        Gate::authorize('update', $lab);
        $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $lifecycle->saveDraft($request->user(), $lab, $request->integer('lock_version'), $request->all());

        return response()->json(['data' => $this->payload($lab->refresh(), $copies, $publish)]);
    }

    /** Throw the draft away and go back to the published lab. */
    public function discardDraft(Request $request, Lab $lab, LabLifecycle $lifecycle, LabWorkingCopy $copies, PublishLab $publish): JsonResponse
    {
        Gate::authorize('update', $lab);
        $lifecycle->discardDraft($request->user(), $lab);

        return response()->json(['data' => $this->payload($lab->refresh(), $copies, $publish)]);
    }

    /**
     * Check a definition without saving.
     *
     * Body: optional definition fields (otherwise the working copy is checked). Runs the schema, the
     * reference checks and replays every solution and decoy, exactly as publishing does. Returns
     * `errors` and `warnings` keyed by path.
     */
    public function check(Request $request, Lab $lab, LabWorkingCopy $copies): JsonResponse
    {
        Gate::authorize('view', $lab);
        $def = $request->has('title') ? $request->all() : $copies->current($lab);
        $report = $copies->report($def, replay: true);

        return response()->json(['data' => $report + ['valid' => $report['errors'] === []]]);
    }

    /**
     * Publish the draft.
     *
     * Body: `lock_version`, optional `note`, and `confirm_restart` when the change restarts learners'
     * attempts and some exist (422 `confirm_restart` says how many). Replays every solution first.
     */
    public function publish(Request $request, Lab $lab, PublishLab $publisher, LabWorkingCopy $copies): JsonResponse
    {
        Gate::authorize('publish', $lab);
        $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:300'], 'confirm_restart' => ['boolean']]);
        $publisher->publish($request->user(), $lab, $request->integer('lock_version'), $request->input('note'), $request->boolean('confirm_restart'));

        return response()->json(['data' => $this->payload($lab->refresh(), $copies, $publisher)]);
    }

    /** Hide the lab from learners; attempts are kept. */
    public function archive(Request $request, Lab $lab, LabLifecycle $lifecycle, LabWorkingCopy $copies, PublishLab $publish): JsonResponse
    {
        Gate::authorize('archive', $lab);
        $lifecycle->archive($request->user(), $lab, true);

        return response()->json(['data' => $this->payload($lab->refresh(), $copies, $publish)]);
    }

    public function unarchive(Request $request, Lab $lab, LabLifecycle $lifecycle, LabWorkingCopy $copies, PublishLab $publish): JsonResponse
    {
        Gate::authorize('archive', $lab);
        $lifecycle->archive($request->user(), $lab, false);

        return response()->json(['data' => $this->payload($lab->refresh(), $copies, $publish)]);
    }

    /** Set the order learners see: every lab id, once, in the order wanted. */
    public function order(Request $request, LabLifecycle $lifecycle): JsonResponse
    {
        Gate::authorize('reorder', Lab::class);
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'distinct']]);
        $lifecycle->reorder($request->user(), array_map('intval', $data['ids']));

        return response()->json(['data' => ['ids' => array_map('intval', $data['ids'])]]);
    }

    /** Published versions, newest first. */
    public function revisions(Lab $lab): JsonResponse
    {
        Gate::authorize('view', $lab);

        return response()->json(['data' => $lab->revisions()->latest('version')->get(['id', 'version', 'note', 'created_by', 'created_at'])->map(fn (LabRevision $r) => [
            'id' => $r->id, 'version' => $r->version, 'note' => $r->note, 'created_by' => $r->created_by, 'created_at' => $r->created_at->toIso8601String(),
        ])]);
    }

    /** Put an earlier version into the draft. Nothing goes live until it is published. */
    public function restore(Request $request, Lab $lab, int $version, LabLifecycle $lifecycle, LabWorkingCopy $copies, PublishLab $publish): JsonResponse
    {
        Gate::authorize('update', $lab);
        $lifecycle->restore($request->user(), $lab, $lab->revisions()->where('version', $version)->firstOrFail());

        return response()->json(['data' => $this->payload($lab->refresh(), $copies, $publish)]);
    }

    /** @return list<string> */
    private function shadowed(): array
    {
        try {
            return LabFiles::read()['shadowed'];
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function payload(Lab $lab, LabWorkingCopy $copies, PublishLab $publish): array
    {
        $lab->unsetRelation('draft');
        $lab->load('draft');

        return [
            'id' => $lab->id,
            'slug' => $lab->slug,
            'status' => $lab->status,
            'is_archived' => $lab->is_archived,
            'position' => $lab->position,
            'has_draft' => $lab->draft !== null,
            'owned' => $lab->updated_by !== null || $lab->draft !== null || $lab->revisions()->exists(),
            'lock_version' => $lab->draft?->lock_version ?? 0,
            'scenario_version' => $lab->scenario_version,
            'definition' => $copies->current($lab),
            'impact' => $publish->impact($lab),
            'domains' => Domain::query()->orderBy('name')->get(['slug', 'name']),
        ];
    }
}
