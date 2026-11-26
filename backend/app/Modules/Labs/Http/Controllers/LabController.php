<?php

namespace App\Modules\Labs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Modules\Labs\Http\Requests\LabActionRequest;
use App\Modules\Labs\Services\LabPresenter;
use App\Modules\Labs\Services\LabSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LabController extends Controller
{
    /** Labs on offer, in order, with my progress in each. */
    public function index(Request $request, LabPresenter $presenter): JsonResponse
    {
        $labs = $this->labs()->orderBy('position')->get();
        $attempts = LabAttempt::query()->where('user_id', $request->user()->id)->whereIn('lab_id', $labs->pluck('id'))->get()->keyBy('lab_id');

        return response()->json(['data' => $labs->map(fn (Lab $lab) => $presenter->summary($lab, $attempts[$lab->id] ?? null))]);
    }

    /**
     * One lab: the briefing, what the learner may see of the scenario, the checkpoints with my
     * progress (never their answers), and my attempt's state, or null before I start.
     */
    public function show(Request $request, string $slug, LabPresenter $presenter, LabSession $session): JsonResponse
    {
        $lab = $this->labs()->where('slug', $slug)->firstOrFail();
        $attempt = LabAttempt::query()->where('user_id', $request->user()->id)->where('lab_id', $lab->id)->first();
        $attempt = $attempt ? $session->sync($attempt, $lab) : null;

        return response()->json(['data' => $presenter->detail($lab, $attempt)]);
    }

    /** Start the lab: 201 the first time, then 200 with my existing attempt. */
    public function start(Request $request, string $slug, LabSession $session, LabPresenter $presenter): JsonResponse
    {
        $lab = $this->labs()->where('slug', $slug)->firstOrFail();
        [$attempt, $created] = $session->start($request->user(), $lab);

        return response()->json(['data' => $presenter->detail($lab, $attempt)], $created ? 201 : 200);
    }

    /**
     * Apply one action (a command, a rule change, a request, or an answer) to my attempt.
     *
     * `version` is the attempt version I last saw; if another tab changed the lab since, this is
     * 409 and nothing changes. Returns the action's output, my updated state and checkpoints.
     */
    public function act(LabActionRequest $request, int $id, LabSession $session, LabPresenter $presenter): JsonResponse
    {
        $attempt = $this->attempt($request, $id, $session);
        // The envelope is validated; the action itself is checked by its engine, so it is passed whole.
        $result = $session->act($attempt, (int) $request->validated('version'), (array) $request->input('action'));
        $lab = $result['attempt']->lab;

        return response()->json(['data' => [
            'output' => (object) $result['output'],
            'newly_passed' => $result['newly_passed'],
            'checkpoints' => $presenter->detail($lab, $result['attempt'])['checkpoints'],
            'attempt' => $presenter->attempt($lab, $result['attempt']),
        ]]);
    }

    /** Start my attempt over (same version check as actions). Completion is kept. */
    public function reset(Request $request, int $id, LabSession $session, LabPresenter $presenter): JsonResponse
    {
        $version = $request->validate(['version' => ['required', 'integer']])['version'];
        $attempt = $session->reset($this->attempt($request, $id, $session), (int) $version);

        return response()->json(['data' => $presenter->detail($attempt->lab, $attempt)]);
    }

    /** Someone else's attempt, or one on an archived lab, is 404. */
    private function attempt(Request $request, int $id, LabSession $session): LabAttempt
    {
        $attempt = LabAttempt::query()->whereKey($id)->where('user_id', $request->user()->id)->firstOrFail();
        $lab = $this->labs()->whereKey($attempt->lab_id)->firstOrFail();
        // A lab changed since this attempt began restarts it; the client's version is then stale (409), so it reloads and sees the notice.
        $attempt = $session->sync($attempt, $lab);
        $attempt->setRelation('lab', $lab);

        return $attempt;
    }

    /** @return Builder<Lab> */
    private function labs(): Builder
    {
        return Lab::query()->offered()->with([
            'domain:id,slug,name',
            'lesson' => fn ($q) => $q->visibleToLearners()->select(['lessons.id', 'lessons.slug', 'lessons.title']),
        ]);
    }
}
