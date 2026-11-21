# CyberCompass: working rules for every session

Module-specific rules live in [backend/CLAUDE.md](backend/CLAUDE.md) (Laravel, Docker commands) and [frontend/AGENTS.md](frontend/AGENTS.md) (Next.js version notes). This file does not repeat them.

## 1. Reading order

Read these in order at the start of every session:

1. `CLAUDE.md` (this file)
2. [docs/HANDOFF.md](docs/HANDOFF.md): current state and the conventions the next phase depends on
3. [docs/IMPLEMENTATION_ROADMAP.md](docs/IMPLEMENTATION_ROADMAP.md)
4. [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), especially the decision table (§9)
5. The docs relevant to the current phase (schema, API, design system, phase design docs such as [docs/PHASE_3_5_CONTENT_AND_ADMIN.md](docs/PHASE_3_5_CONTENT_AND_ADMIN.md))
6. `docs/private/MASTER_PROMPT.md`: the original brief, kept local only and git-ignored. **If it is missing, say so and ask the user for it. Never guess its contents.**

## 2. Precedence

When documents conflict, the deliberate refinements win over the original brief:

- ARCHITECTURE.md decisions (D-numbers), IMPLEMENTATION_ROADMAP.md and the phase design docs override the master prompt.
- The master prompt's strict development rules (its §13) always apply.

## 3. Master prompt items already delivered or superseded

Check this table before building anything. Later phases reuse or extend what is listed here; they do not rebuild it.

| Brief item | Status | Where it lives | Later phases must |
|---|---|---|---|
| Lesson content model (the brief's `lesson_contents`) | Superseded | `lesson_sections` as typed blocks with stable UUIDs (D17, D18, D20); `app/Modules/Content` (`BlockRegistry`, one JSON Schema per type, shared fixtures checked by Pest and Vitest) | Add a block type through the registry, its zod mirror and fixtures. Never write a second renderer or validator. Reference sections by UUID. |
| Visual explanations, interactive diagrams, step-by-step walkthroughs | Delivered (3.5 C2) | 19 block types; renderers in `frontend/src/features/learning/blocks` | Reuse the blocks. Only add a block when no existing type fits the knowledge shape ([CONTENT_AUTHORING_GUIDE.md](docs/CONTENT_AUTHORING_GUIDE.md)). |
| 3D demonstrations (packet transmission, topology) | Delivered (3.5 C1, C3), changed approach | `scene-3d` and `script-3d` on a sandboxed Three.js runtime (D22, [SANDBOXED_3D.md](docs/SANDBOXED_3D.md)). No React Three Fiber. The networking intro lesson already has a "follow a packet" scene. | Build new 3D as content on these blocks. The Phase 5 "one 3D visual" item is struck from the roadmap as delivered. |
| Graph library for diagrams | Changed; decided for the roadmap (D27) | Lesson diagrams are static SVG from `frontend/src/lib/graph` (dagre `layoutGraph()`, D23). React Flow is not used anywhere and is not installed. | The roadmap graph and knowledge map are HTML nodes over static SVG edges (D27), reusing `lib/graph`, the career map and the lesson graph blocks. Keep layout separate from rendering; no pan or zoom canvas. Revisit only past about 60 to 100 nodes or for a drag-and-drop admin roadmap editor. |
| Roadmap node statuses (locked, available, in progress, completed, review) | Visual delivered (Phase 3) | `frontend/src/features/roadmap/components/node-status.tsx`; career template graph in `features/careers/components/career-map.tsx` | Reuse `RoadmapNodeCard` for every personal roadmap view (the Phase 4 outline does). Status is computed by the API (ARCHITECTURE §6.3), never in the UI. |
| Seed and demonstration data | Changed | Content files in `backend/database/seeders/content/`; `ContentImporter` upserts by slug and UUID, archives instead of deleting, and skips admin-owned lessons (D19); `content:import` and `content:export` | New seeded content (questions, exercises, labs) upserts by a stable key and is archived, never deleted, once anything refers to it. |
| Admin console | Added (not in the brief) | `app/Modules/Admin`, `app/Modules/Content` admin routes, `frontend/src/app/(admin)`, `features/admin` (D16) | A new admin area is a page under `(admin)/admin/<area>`, one entry in `admin-nav.tsx`, routes behind `auth:sanctum`, `EnsureAdmin` and `throttle:admin`, a policy per action and an audit log entry per change. |
| Practice questions inside lessons | Delivered as `knowledge-check` (ungraded, answers ship with the lesson) | Block type `knowledge-check` | Never count them toward completion, and never reuse them as assessment or exercise items. Graded answer keys stay server-side. |
| Learner visibility of lessons | Delivered | `Lesson::scopeVisibleToLearners` | Every learner query (roadmaps, search, dashboards) goes through it. |
| Uploads (avatars, lesson images) | Delivered | Re-encoded to WebP on the `uploads_disk` (S3 in production) | Use the same disk and re-encoding for any new upload. |
| Course catalog, course detail, lesson reader | Delivered (Phase 3) | `Catalog` and `Learning` modules; `features/catalog`, `features/learning` | Extend these; exercises (Phase 5) attach to the existing reader. |
| Careers catalog and detail with roadmap template | Delivered (Phase 3); goal selection delivered (Phase 4) | `Careers` module; `features/careers`; `careers.php` and `topics.php` content files (templates are validated prerequisite-closed and stage-ordered) | `CareerGoalPanel` sets the goal on the career page; extend it rather than adding another goal control. |
| Auth, profile, preferences, en/vi | Delivered (Phase 2) | `Auth`, `Profile` modules; settings tabs (personal, learning goals, security, preferences); next-intl | Notification preferences arrive in Phase 6 as another settings tab. |
| Assessment question bank | Decided (Phase 4, D24) | `Assessment` module and `seeders/content/assessment.php`, keyed questions, archived not deleted | A question admin or a larger pool extends this module and file. The result reveals answers only for wrong questions (D26). |
| Personal roadmap view | Delivered (Phase 4, D25) | Outline at `/roadmap` (`features/roadmap/components/roadmap-view.tsx`) on `GET /learning-path` | Phase 5 adds the graph on the same response and keeps the outline for phones. |
| Roadmap generation and node status | Delivered (Phase 4) | `Roadmap\Services\RoadmapGenerator` (rules, ARCHITECTURE §6.2), `Roadmap\Services\PathProgress` (status, completion, next lesson, estimate, outdated reasons), `Roadmap\Actions\GenerateLearningPath` | Phase 5 graph and Phase 6 dashboard (`/progress/next`, roadmap %) read from `PathProgress`; never recompute status elsewhere. Only the explicit generate action creates versions (D26). |
| Learning goal (career, self-rating, weekly time, interests) | Delivered (Phase 4) | `learning_goals`, `PUT /me/learning-goal`, `learning_goal` on `/me`, settings tab `/settings/goals` | Interests order assessment next steps; weekly time drives the estimate; the self-rating is display only. |
| Assessment domain scores | Delivered (Phase 4) | `Assessment\Services\AttemptResult::scores()` over `ScoringService` | Phase 6 skill charts and history reuse these; never re-score on the client. |
| Entity list in the brief (§6) | Normalized | [DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md) normalization notes: `domains` replace categories, paths hold topics, no stored path progress | Follow the schema doc, not the brief's entity list. |

## 4. Extend, never duplicate

Before implementing anything in a phase, check the table above and the existing modules (`backend/app/Modules`, `frontend/src/features`, `frontend/src/lib`). Extend what exists.

## 5. Unplanned phases and mid-phase changes

This protocol applies to any phase or feature not already in IMPLEMENTATION_ROADMAP.md (as Phase 3.5 was), and to changes the user asks for in the middle of a planned phase.

1. **Impact check before any code.** Compare the work against every earlier and later phase: the roadmap, ARCHITECTURE decisions, the table in §3, the existing modules and the master prompt.
2. **Classify each finding:**
   - **Duplicate:** the work already exists, or is planned in another phase.
   - **Conflict:** the work contradicts an existing decision, schema, API contract or convention, or would break or force rework of a later phase.
   - **Shift:** the work pulls scope forward from a later phase, or pushes scope back to one.
3. **No findings:** document the phase in IMPLEMENTATION_ROADMAP.md (numbered between existing phases, for example 4.5) with its impact notes, then proceed.
4. **Any Duplicate or Conflict: stop and ask the user before implementing.** For each finding, present:
   - what overlaps or conflicts, and where (files, phase, decision ID)
   - 2 or 3 options (for example: reuse or extend the existing part, change the earlier decision, move the work to another phase, drop it)
   - the pros and cons of each, including the effect on later phases and on AWS deployment
   - the recommended option and why

   State a **Shift** clearly in the plan. Ask only if it materially changes another phase's scope.
5. **After the user decides:** record the decision in ARCHITECTURE.md as a new decision ID, update the roadmap so the affected phases no longer overlap, and update the table in §3.

## 6. End of every phase

Before reporting a phase as done, update:

- [docs/HANDOFF.md](docs/HANDOFF.md)
- the phase report in [docs/IMPLEMENTATION_ROADMAP.md](docs/IMPLEMENTATION_ROADMAP.md)
- the table in §3 of this file

The phase gate itself (tests, typecheck, real-browser check, docs, report) is defined at the top of the roadmap.
