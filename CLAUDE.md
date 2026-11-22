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

### Tools

- **Ponytail** (plugin) runs at level `lite`. **Graphify** (skill) output in `graphify-out/` is local only and git-ignored: it can contain text from `docs/private/`.
- This file, the ARCHITECTURE decisions and the master prompt's strict rules override any plugin's or skill's style guidance.
- Ponytail's "smallest solution" never removes validation, security, accessibility, i18n or tests that our docs require.

## 3. Master prompt items already delivered or superseded

Check this table before building anything. Later phases reuse or extend what is listed here; they do not rebuild it.

| Brief item | Status | Where it lives | Later phases must |
|---|---|---|---|
| Lesson content model (the brief's `lesson_contents`) | Superseded | `lesson_sections` as typed blocks with stable UUIDs (D17, D18, D20); `app/Modules/Content` (`BlockRegistry`, one JSON Schema per type, shared fixtures checked by Pest and Vitest) | Add a block type through the registry, its zod mirror and fixtures. Never write a second renderer or validator. Reference sections by UUID. |
| Visual explanations, interactive diagrams, step-by-step walkthroughs | Delivered (3.5 C2) | 19 block types; renderers in `frontend/src/features/learning/blocks` | Reuse the blocks. Only add a block when no existing type fits the knowledge shape ([CONTENT_AUTHORING_GUIDE.md](docs/CONTENT_AUTHORING_GUIDE.md)). Blocks lay out by container queries (`@container` on `Section`), so new blocks use `@xl:`-style variants, not viewport breakpoints. |
| 3D demonstrations (packet transmission, topology) | Delivered (3.5 C1, C3), changed approach | `scene-3d` and `script-3d` on a sandboxed Three.js runtime (D22, [SANDBOXED_3D.md](docs/SANDBOXED_3D.md)). No React Three Fiber. The networking intro lesson already has a "follow a packet" scene. | Build new 3D as content on these blocks. The Phase 5 "one 3D visual" item is struck from the roadmap as delivered. |
| Graph library for diagrams | Changed; decided for the roadmap (D27) | Lesson diagrams are static SVG from `frontend/src/lib/graph` (dagre `layoutGraph()`, D23). React Flow is not used anywhere and is not installed. | The roadmap graph and knowledge map are HTML nodes over static SVG edges (D27), reusing `lib/graph`, the career map and the lesson graph blocks. Keep layout separate from rendering; no pan or zoom canvas. Revisit only past about 60 to 100 nodes or for a drag-and-drop admin roadmap editor. |
| Roadmap node statuses (locked, available, in progress, completed, review) | Visual delivered (Phase 3) | `frontend/src/features/roadmap/components/node-status.tsx`; career template graph in `features/careers/components/career-map.tsx` | Reuse `RoadmapNodeCard` for every personal roadmap view (the Phase 4 outline does). Status is computed by the API (ARCHITECTURE §6.3), never in the UI. |
| Seed and demonstration data | Changed | Content files in `backend/database/seeders/content/`; `ContentImporter` upserts by slug and UUID, archives instead of deleting, and skips admin-owned lessons (D19); `content:import` and `content:export` | New seeded content (questions, exercises, labs) upserts by a stable key and is archived, never deleted, once anything refers to it. |
| Admin console | Added (not in the brief) | `app/Modules/Admin`, `app/Modules/Content` admin routes, `frontend/src/app/(admin)`, `features/admin` (D16) | A new admin area is a page under `(admin)/admin/<area>`, one entry in `admin-nav.tsx`, routes behind `auth:sanctum`, `EnsureAdmin` and `throttle:admin`, a policy per action and an audit log entry per change. |
| Practice questions inside lessons | Delivered as `knowledge-check` (ungraded, answers ship with the lesson) | Block type `knowledge-check` | Never count them toward completion, and never reuse them as assessment or exercise items. Graded answer keys stay server-side. |
| Learner visibility of lessons | Delivered | `Lesson::scopeVisibleToLearners` | Every learner query (roadmaps, search, dashboards) goes through it. |
| Uploads (avatars, lesson images) | Delivered; S3 verified in Phase 7 Part F | Re-encoded to WebP on the `uploads_disk` (S3 in production) | Use the same disk and re-encoding for any new upload. |
| Course catalog, course detail, lesson reader | Delivered (Phase 3) | `Catalog` and `Learning` modules; `features/catalog`, `features/learning` | Extend these; exercises (Phase 5) attach to the existing reader. |
| Careers catalog and detail with roadmap template | Delivered (Phase 3); goal selection delivered (Phase 4) | `Careers` module; `features/careers`; `careers.php` and `topics.php` content files (templates are validated prerequisite-closed and stage-ordered) | `CareerGoalPanel` sets the goal on the career page; extend it rather than adding another goal control. |
| Auth, profile, preferences, en/vi | Delivered (Phase 2) | `Auth`, `Profile` modules; settings tabs (personal, learning goals, security, preferences, notifications, the last from Phase 6); next-intl | Add a settings area as another tab in `settings-nav.tsx`. |
| Assessment question bank | Decided (Phase 4, D24) | `Assessment` module and `seeders/content/assessment.php`, keyed questions, archived not deleted | A question admin or a larger pool extends this module and file. The result reveals answers only for wrong questions (D26). |
| Personal roadmap view and knowledge map | Delivered (Phase 4 outline D25; Phase 5 graph and map D27) | `/roadmap` (`roadmap-view.tsx`, `roadmap-graph.tsx`), `/roadmap/map` (`knowledge-map-view.tsx`, `GET /knowledge-map`); shared `TopicGraph`, `LessonDrawer`, `OutlineTopic`; layout in `lib/graph/grid.ts` | Draw any topic graph with `layoutGrid()` and `TopicGraph`; keep the outline below 48rem. Phase 6 `/progress/skills` reuses `KnowledgeMap::domains()`. |
| Roadmap generation and node status | Delivered (Phase 4) | `Roadmap\Services\RoadmapGenerator` (rules, ARCHITECTURE §6.2), `Roadmap\Services\TopicStatus` (the status rule), `PathProgress` (completion, next lesson, estimate, outdated reasons), `Roadmap\Actions\GenerateLearningPath` | Phase 5 graph and Phase 6 dashboard (`/progress/next`, roadmap %) read from `PathProgress`; never recompute status elsewhere. Only the explicit generate action creates versions (D26). |
| Learning goal (career, self-rating, weekly time, interests) | Delivered (Phase 4) | `learning_goals`, `PUT /me/learning-goal`, `learning_goal` on `/me`, settings tab `/settings/goals` | Interests order assessment next steps; weekly time drives the estimate; the self-rating is display only. |
| Learning events | Delivered (Phases 4 to 6) | `AssessmentSubmitted`, `ExerciseSubmitted`, `LabCompleted`, `LessonStarted`, `LessonCompleted` (from `RecordLessonProgress`), all after commit; listeners registered in `AppServiceProvider` | Add side effects as listeners on these events, never in controllers. |
| Learning activity, streaks, progress API | Delivered (Phase 6 Part A, D33) | `learning_activities` written only by `Progress\Services\ActivityRecorder`; `LearnerClock` (stored timezone), `StreakCalculator`, `ProgressReport`; `/progress/*`; `progress:backfill` | Read activity figures from `ProgressReport`; use `LearnerClock` for any "today" (reminders, milestones). |
| Assessment domain scores | Delivered (Phase 4) | `Assessment\Services\AttemptResult::scores()` over `ScoringService` | Phase 6 skill charts and history reuse these; never re-score on the client. |
| Exercises (brief Module F exercise mode) | Delivered (Phase 5 Parts B and C, D29 to D31) | `Learning` module: `QuestionTypes` (every type rule), `ExerciseLoader` over `seeders/content/exercises/<course>.php`, `SubmitExercise`, `ExercisePresenter`; screen `features/exercise` at `/learn/[slug]/exercise` (theory panel, seven inputs, result); theory references by section key or UUID; `passLesson()` test helper | Keys stay server-side until submission. Sections referenced by an active question cannot be deleted (D30). A passing attempt completes the lesson (D31). |
| Practical labs (brief Module G) | Delivered (Phase 5 Part D, D28) | `Labs` module: engines in `Labs/Engines` (`LinuxShellEngine`, `FirewallRulesEngine`, `HttpInspectorEngine`) behind `SimulatedLabProvider`, `LabSession` (lock, version, caps, completion), `LabLoader` over `seeders/content/labs.php`; `features/labs`; security rules in ARCHITECTURE §7.1 | Never execute processes or touch the real filesystem: the token scan in `tests/Unit/Labs/EngineSafetyTest.php` must pass for any new engine. Learner patterns go through `SafeRegex`. A new engine implements `LabEngine` and registers in `SimulatedLabProvider`; answer checkpoints come free. |
| Charts (brief: Recharts) | Delivered (Phase 6 Part B, D32) | Own SVG chart components in `frontend/src/lib/charts`, each with a data table | Draw any new chart with these components (rules in DESIGN_SYSTEM §6 Charts); do not install a chart library without revisiting D32. |
| Global search (brief navigation item) | Delivered (Phase 6 Part E, D36) | `Search` module (`GET /search`, `GlobalSearch`), matching in `App\Support\Search\Like` (also used by the catalog); header dialog in `features/search` | Search new content kinds by adding a group to `GlobalSearch` through `Like`; lessons only through `Lesson::scopeVisibleToLearners`. |
| Learner dashboard (brief Module H) | Delivered (Phase 6 Part B, D35) | `/dashboard` (`features/progress`), the page after login; `TimezoneSync` in the signed-in layout; timezone field under Preferences | Add dashboard panels inside `DashboardView` reading `/progress/*`; never compute progress in the UI. |
| Notifications (brief Module J) | Delivered (Phase 6 Parts C and D, D34) | `Notifications` module: `LearningNotification` (one class, type plus params plus path), `NotificationPreferences`, `SendOnce`, `NotifyLearner`, `RecommendNewLesson`, `notifications:send-reminders` (hourly, `scheduler` service); milestones in `Progress\Services\MilestoneDetector` (`MilestoneReached`); UI in `features/notifications` (header bell, `/notifications`, `/settings/notifications`) | A new notification is a new `NotificationType` case, a listener that sends `LearningNotification`, mail text in `lang/*/notifications.php` and app wording in `useNotificationText` with the `notifications.items` messages. One-off sends claim a key through `SendOnce`. |
| Critical workflow tests (brief §9, frontend) | Delivered (Phase 7 Part A, D45) | Playwright suite in `frontend/e2e` (`npm run e2e`), throwaway accounts from `cybercompass:e2e-users`, `apiGet` and the console guard in `e2e/support/test.ts` | Add a spec per new critical flow under `frontend/e2e`, signed in as its own account. Never read answer keys from the database; pass exercises from the post-submission reveal. Modals use `components/ui/dialog.tsx` (it restores focus to the opener). |
| CI (Phase 7 decision D46) | Delivered (Phase 7 Part A2) | `.github/workflows/ci.yml`, `.github/scripts/` | A new test command or service joins the existing jobs; keep the backend and E2E jobs on the compose stack so CI matches local development. |
| Security review, headers and CSP (brief §9) | Delivered (Phase 7 Parts B and C, D42) | [docs/SECURITY_REVIEW.md](docs/SECURITY_REVIEW.md); `RouteInventoryTest`; `cybercompass:preflight`; `src/proxy.ts` with `lib/security/csp.ts`; `SecurityHeaders` middleware; `e2e/security.spec.ts` | A new public route goes on the inventory's public list on purpose; a new external origin goes into `csp.ts`; a new production setting gets a preflight check. Never trust forwarded headers beyond the ALB (S9). |
| Performance (brief Phase 7) | Delivered (Phase 7 Part D) | [docs/PERFORMANCE.md](docs/PERFORMANCE.md); `tests/Feature/Performance/QueryBudgetTest.php`; `e2e/performance.spec.ts` (enforced in CI); `src/lib/zod-config.ts` | A new hot endpoint gets a query budget; a new page that matters gets a weight and API budget; check a new dependency with `npx next analyze`. |
| Production build and release (brief §10) | Delivered (Phase 7 Part F, D37 to D39) | `cybercompass:release` (preflight, migrate, content), `QueueMetrics` and the `metrics` log channel (D48), `docker-compose.prod.yml`, `*/.env.production.example`, the S3 adapter | Uploads never pass a visibility option (the private bucket refuses ACLs). A new production variable goes into the `.env.production.example` file and, if unsafe when wrong, a preflight check. Run `npm run lock:linux` after changing frontend dependencies on Windows. |
| AWS deployment documentation (brief §10) | Delivered (Phase 7 Part G, D37 to D50); the deployment itself is run by the owner, outside the roadmap (D54) | [docs/DEPLOYMENT_AWS.md](docs/DEPLOYMENT_AWS.md); `infrastructure/aws/` (`config.example.sh`, `templates/`, `scripts/`) | Change AWS resources through these templates and scripts, never only in the console; a new production variable goes into the task definition templates and `.env.production.example`. Never commit `config.sh`. |
| Compass menu and keyboard shortcuts (owner request, F1, formerly Phase 7.5) | Delivered (D51 to D53) | `lib/navigation.ts` (primary links, `isActive`), `SETTINGS_TABS`, `ADMIN_AREAS`; shortcut registry `lib/shortcuts` (`ACTIONS`, `useShortcut`, `typing`) with overrides from `features/shortcuts/prefs.tsx`; `user_profiles.compass_enabled` and `shortcuts`; the compass in `features/compass` (`useCompassItems`, `useCompassPageItems`), geometry in `lib/radial`; settings tab `/settings/shortcuts` | Read routes from these lists, never retype one. A page adds "On this page" items through `useCompassPageItems`, never by reading the DOM from the compass. A new shortcut is an action in `ACTIONS` used through `useShortcut`; never add a raw `keydown` listener for a global shortcut. |
| Blog (owner request, F2) | Delivered (Part A, D55, D56) | `Blog` module and the `posts`, `post_sections`, `post_drafts`, `post_revisions` tables on the block-document core in `Content/Documents` (`BlockDocument`, `WorkingCopy`, `DocumentDrafts`, `DocumentPublisher`, `ReadingTime`); `features/blog`, `DocumentLayout` (shared reader layout), `DocumentEditor` with an adapter per type; `/blog`, `/blog/[slug]`, `/admin/posts`; posts in `seeders/content/posts/<slug>.php`, images by file through `ContentFileMedia` | A new long-form document type reuses the core (twin tables, a `WorkingCopy` subclass, an editor adapter), never a second editor, reader or validator. Every public post query goes through `Post::scopeVisibleToReaders`. Posts never feed progress, learning events, roadmaps or notifications. |
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
3. **No findings:** document the work in IMPLEMENTATION_ROADMAP.md as a new feature id F<n> in the Feature track, with its impact notes, then proceed.
4. **Any Duplicate or Conflict: stop and ask the user before implementing.** For each finding, present:
   - what overlaps or conflicts, and where (files, phase, decision ID)
   - 2 or 3 options (for example: reuse or extend the existing part, change the earlier decision, move the work to another phase, drop it)
   - the pros and cons of each, including the effect on later phases and on AWS deployment
   - the recommended option and why

   State a **Shift** clearly in the plan. Ask only if it materially changes another phase's scope.
5. **After the user decides:** record the decision in ARCHITECTURE.md as a new decision ID, update the roadmap so the affected phases no longer overlap, and update the table in §3.

## 6. End of every phase or feature

Before reporting a phase or feature as done, update:

- [docs/HANDOFF.md](docs/HANDOFF.md)
- the phase or feature report in [docs/IMPLEMENTATION_ROADMAP.md](docs/IMPLEMENTATION_ROADMAP.md)
- the table in §3 of this file

The gate itself (tests, typecheck, real-browser check, docs, report) and the deploy-ready gate every feature must pass (D54) are defined at the top of the roadmap.
