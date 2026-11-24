# Handoff

Where the project stands and what the next phase needs to know. This page only points to the docs that hold the detail. Start with the reading order in the root [CLAUDE.md](../CLAUDE.md).

## Current state (2026-11-24)

- **The initial build (v1), Phases 1 to 7, is complete.** Phase 7 closed with Parts A to G: the E2E suite, CI, the security review ([SECURITY_REVIEW.md](SECURITY_REVIEW.md)), performance ([PERFORMANCE.md](PERFORMANCE.md)), the responsive, accessibility and Hallmark sweep, the production build and the AWS guide and templates ([DEPLOYMENT_AWS.md](DEPLOYMENT_AWS.md)). Phase-by-phase results: [IMPLEMENTATION_ROADMAP.md](IMPLEMENTATION_ROADMAP.md).
- **AWS work for features after v1 is deferred (D72):** F6 finishes on the development stack; what the first deployment must add is in [Deferred until first deployment](IMPLEMENTATION_ROADMAP.md#deferred-until-first-deployment) (also linked from DEPLOYMENT_AWS).
- **Deployment is not roadmap work (D54).** The owner deploys on their own schedule with DEPLOYMENT_AWS.md and `infrastructure/aws/`. Nothing has been run against AWS yet.
- **Work after v1 is the [Feature track](IMPLEMENTATION_ROADMAP.md#feature-track):** stable ids `F<n>`, commits `type(scope): summary (F<n> Part X)`, and every feature passes [the gate](IMPLEMENTATION_ROADMAP.md#the-gate) including the deploy-ready gate, reported under "Deploy notes".
- **F1 (compass menu and keyboard shortcuts, formerly Phase 7.5) is complete** (D51 to D53).
- **F2 (Blog) is complete** (D55, D56): the feature (Part A) and the first post, "Docker & Containers 101" (Part B, `content/posts/docker-containers-101.php`, every block type).
- **F3 (Course map), Parts A and B are done** (D57 to D60): data, validation, import and export, API and health check, then the renderer, focus mode and admin panels. Part C (maps for the other seven courses) is dropped (D66): only Networking Fundamentals has a map, and a course without one simply shows none.
- **F6 (Content packages) is in progress** (D67 to D71). Part A is done: section `sources` (validated once in `Content\Documents\SectionSources`, called by `BlockRegistry::validateSections`) and the callout tones `recap` and `fun-fact`. Part B is done: `Content/Packages` (`PackageReader`, `PackagePlanner`, `ImportPackage`, `PackageExporter`, `Fingerprint`) and `content:package validate|import|export`; a package only ever writes drafts through the working copy. Part E is done: the course overview, the generated AI authoring guide (`content:format-guide`, checked by a test) and the OSI example in `backend/resources/content-format/` (see [docs/content-format](content-format/README.md)). Part D is done: import and export in every lesson and post editor and on the course page (`features/content-packages`), with polling back-off, a stalled-worker notice and "Copy errors for the chatbot". Part C is done: `content_transfers`, `TransferStorage` (S3 presigned multipart in production, local parts in development), the queued `ValidateTransfer`, `ApplyTransfer` and `ExportTransfer` (each reads the file from storage into its own folder and always removes it), idempotent apply, `content:prune-transfers` hourly. Parts D to F (package services and CLI, API and storage, admin UI, overview and AI guide, AWS) follow, in the roadmap's F6 section.
- **F5 (Networking exercises) is done:** graded exercises for the 14 lessons F4 added (73 questions), reviewed by an independent subagent; every networking lesson now has an exercise.
- **Course map UI round after F4 is done** (D64, D65): the problem in a tooltip, spread edge ends and hover highlights, lines routed around every node in focus mode, and one style map for every line with an interactive legend. Details in the roadmap's F4 section.
- **F4 (Networking Fundamentals, expanded) is done** (D61 to D63): 20 lessons in 5 chapters with three new topics, architectures and the course map, the wide-map layout, and rule-based Course map suggestions for admins.
- **Tests:** 1033 Pest, 553 Vitest and 37 Playwright end-to-end tests (after F6 Part E, 2026-11-24).
- **Running Pest:** `php artisan test --compact`, never `--parallel`. The database user has rights only on `cybercompass` and `cybercompass_testing`, so ParaTest cannot create its per-process databases and a parallel run fails most tests. One such run (2026-11-24) also deleted `database/seeders/content`: `PostImportTest`'s cleanup removed the content root its failed setup never redirected. The test now redirects first and deletes only its own folder; if content files ever show as deleted, restore them with `git checkout -- backend/database/seeders/content` before running anything else. Pint, tsc, ESLint and Hallmark sloplint are clean, and the production build passes.

## Read first

| Topic | Doc |
|---|---|
| Session rules, precedence, what is already delivered | [CLAUDE.md](../CLAUDE.md) |
| Architecture and every decision (D1 to D72) | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Assessment scoring and roadmap rules | [ARCHITECTURE §6](ARCHITECTURE.md#6-personalization-engine) |
| API contract | [API_SPECIFICATION.md](API_SPECIFICATION.md) |
| Schema | [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) |
| Content platform and admin console design (C1 to C21) | [PHASE_3_5_CONTENT_AND_ADMIN.md](PHASE_3_5_CONTENT_AND_ADMIN.md) |
| Sandboxed 3D: threat model and limits | [SANDBOXED_3D.md](SANDBOXED_3D.md) |
| Writing lessons, import and export | [CONTENT_AUTHORING_GUIDE.md](CONTENT_AUTHORING_GUIDE.md) |
| UI system | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) |
| Backend commands and module rules | [backend/CLAUDE.md](../backend/CLAUDE.md) |
| Next.js version notes | [frontend/AGENTS.md](../frontend/AGENTS.md) |

## What Phase 7 builds on

- **Services to deploy:** `api`, `web` and `worker` (D37: notifications and the lesson recommendation fan-out are queued). The worker task also runs `schedule:work` as a second container (D38; it replaced the planned EventBridge rule). The only scheduled task is `notifications:send-reminders`, hourly. Deployment layout: ARCHITECTURE §8 and D37 to D45.
- **Mail:** Mailpit locally; the SES API with the task role in production (D39). Email is opt-in per notification type (D34), so most learners receive none.
- **Rate limits to review:** `api` 120 per minute per user (one dashboard load makes 8 requests and the bell polls once a minute), `search` 60, `lab-actions` 60, `exercise-attempts` 20, `admin` 120, `uploads` 10, `register` 10 per hour, `password` 5.
- **End-to-end suite (D45):** `frontend/e2e`, run with `npm run e2e` against the Docker API and the app on port 3000 (Edge locally; `E2E_CHANNEL=` for bundled Chromium). Global setup makes throwaway accounts with `php artisan cybercompass:e2e-users` (local and testing only) and teardown purges every `e2e-*@example.test` account, including the learner the journey registers through the form. Every page fails its test on a console error unless the spec lists the expected status. Specs: the learner journey (register, assessment, goal, roadmap, exercise failed then passed from the revealed answers, dashboard, bell), auth edges, labs (refusals, completion, the 409), search, the admin console, and a phone layout pass.
- **CI (D46):** `.github/workflows/ci.yml`, three jobs on every pull request and push to `main`; the backend and E2E jobs use the compose stack (`.github/scripts/`). `npm run typecheck` runs `next typegen` first, so it works on a fresh checkout. Regenerate `package-lock.json` in a Linux container if `npm ci` fails on Linux (Windows drops Linux-only optional packages).
- **CSP (D42):** every page gets a nonce policy from `src/proxy.ts`. A new external origin (a script, image or API host) goes into `lib/security/csp.ts`; an inline `<script>` needs the nonce (`x-nonce` header, as `Providers` passes it to next-themes). `/sandbox` keeps its own policy.
- **Production settings the deployment must supply:** `INTERNAL_API_TOKEN` (same value on api and web), `CSP_MEDIA_ORIGIN` (the uploads CloudFront origin) and everything `cybercompass:preflight` checks.
- **Production build (Part F):** the deployment task runs `php artisan cybercompass:release`; the production images run locally with `docker-compose.prod.yml` (README); every production variable is in `*/.env.production.example`. The queue metric line is `{"message":"queue","context":{"pending","reserved","failed","oldest_pending_seconds"}}` on the scheduler's output (D48).
- **AWS (Part G):** follow [DEPLOYMENT_AWS.md](DEPLOYMENT_AWS.md) in order; account values live only in `infrastructure/aws/config.sh` (git-ignored). `deploy.sh` is the one deployment procedure, by hand and from GitHub. Activate CD by copying `infrastructure/aws/templates/github/deploy.yml` into `.github/workflows/` only after the OIDC role exists (step 16).
- **Accessibility guard:** `e2e/sweep.spec.ts` covers every route; add a new route to its lists. The rules it enforced are in DESIGN_SYSTEM §10.
- **Performance guards:** `QueryBudgetTest` (a new hot endpoint gets a budget there) and `performance.spec.ts` (budgets per route, enforced in CI). A module that uses zod at runtime imports `@/lib/zod-config` (the CSP forbids zod's eval probe); never re-export `z` from another module, which defeats tree-shaking.
- **Security guards to keep green:** `RouteInventoryTest` (a new public route goes on its list on purpose; a new expensive route gets a limiter there) and `cybercompass:preflight` (a new production setting gets a check).
- **E2E rules:** each spec signs in as its own account (the `api` limit is per user: reloading the dashboard in a loop hits 120 a minute; poll the API instead with `apiGet`); register through the UI at most once per run (the limit is 10 an hour per IP); never read answer keys from the database.

## F6: content packages (in progress, 2026-11-24)

- **Shared fix in Part B, felt in the editor too:** while a lesson's blocks are invalid, `LessonWorkingCopy` checks architecture references against the section ids sent, instead of an empty list. Before, one bad block also reported every architecture node as pointing nowhere.
- **Keys are not stored:** a section's key is its heading as a slug (`ContentImporter::sectionKeys`), so a renamed heading gives a new key while the uuid stays. Packages may name sections by `uuid`; the planner warns when a package key will read back differently.
- **Transfers never share temporary files** between steps or containers: each job reads from the transfers disk into `storage/app/tmp/packages/transfer-<id>-<step>` and removes it, also from `failed()`. On AWS that disk must be the S3 `transfers` bucket (preflight).
- **Request body limits** are set in the Dockerfile's base stage (16M bodies, 8M files) and guarded by `ContentTransferLimitsTest`; feature tests never pass through nginx or PHP-FPM, so only the Playwright spec proves a real part goes through.
- **E2E:** `content-packages.spec.ts` signs in as `packagesAdmin` (global setup now makes three admins) and works in System Hardening, a course no other spec uses, so its published `e2e-pkg-*` lesson never shows up in parallel specs; the purge removes it and the transfers.
- **The AI guide is generated:** edit `AI_AUTHORING_GUIDE.source.md`, a block schema or a fixture, then run `docker compose exec -u root api php artisan content:format-guide`; Pest fails while the committed guide is stale. Its copy of CONTENT_AUTHORING_GUIDE §1, 2, 3 and 5 must match the doc (Vitest `guide-includes.test.ts`): change the doc, copy the section into the source, regenerate.
- **Regenerate the example overview** after the Networking Fundamentals content changes: `content:package overview --course=networking-fundamentals --out=resources/content-format/examples/networking-fundamentals.overview.md` (as root, on a dev database without leftover `e2e-*` lessons). The example package test pins the OSI lesson's fingerprint, so it fails when that lesson changes; update the package's `base` then.
- **Test helpers are global functions** across Pest files; `ContentTransferTest` uses `asTransferAdmin()` because `PostTest` already has `asAdmin()`.

## F5: Networking exercises (2026-11-24)

- **Writing more questions:** follow the rules in the roadmap's F5 section (one-token command blanks, one recall question per lesson, defender-side scenarios, explanations of the tempting wrong option). Read the lesson's knowledge checks first, so no graded question repeats one.
- **E2E admins:** global setup makes two; `account("mapAdmin")` is the course map suggestions spec's own, because the admin limit is per user. Give a new admin-heavy spec its own admin the same way rather than raising a limit.
- **Checking a batch:** run the seeder (`db:seed --class='Database\Seeders\ExerciseSeeder'`), which validates every question and section reference; then pass each exercise in the browser from its revealed answers. One learner loading many lessons in a row hits the `api` limit of 120 a minute: pace about 25 seconds per lesson.

## Course map UI round after F4 (2026-11-24)

- **Where the rules live.** Line styles: only `EDGE_STYLES` (`features/course-map/components/edge-style.tsx`); draw a line with `EdgePath` and the arrowheads with `EdgeMarkers` in the same document, never with hand-set stroke attributes. A caller's `style` is merged under the kind's colour, so passing an opacity never drops it.
- **Routing.** Lesson-level lines: `lib/graph/ortho.ts` (focus mode; the overview keeps its curves, `ROUTE_OVERVIEW` in `layout.ts`). Lines between parts: `lib/graph/shapes/route.ts`, called by every shape layout for authored relations. Both are pure; keep any new obstacle (a label, a control) in the obstacle list rather than drawing over it.
- **Ports** come from `spreadPorts` in `features/course-map/layout.ts`; a new kind of line that meets a node side adds its ends there so arrowheads stay apart.
- **Measuring calls.** `layoutCourseMap(..., { route: false })` for layouts used only for their size.
- **E2E timing.** The map settles once its box is measured after hydration, which can move a node from under a stationary pointer: hover checks retry the hover with every assertion inside `expect(...).toPass()`.
- **Screenshot scripts** that remove a React-owned node (a tooltip) break the page; hide it with CSS instead. Full-page captures paint the sticky header mid-image; hide `header` for the capture.

## F4: Networking Fundamentals, expanded (2026-11-24)

- **Suggestions (D63):** `CourseMapSuggestions` is the only place the rules live; the panel and `content:check-graphs` show its output, and accepting goes through the normal save. Dismissals are in local storage (`cybercompass.dismissedSuggestions.*`).
- **E2E sign-in:** specs use `signIn()` (one form login per account and run, then the saved session); only the specs about signing in use `login()`. The purge removes maps saved by e2e accounts and lessons named `e2e-*`.

- **Course:** 20 lessons, 5 chapters, 335 minutes; the six original lessons are unchanged byte for byte.
- **Topics** (D62): `local-networks`, `routing-and-transport`, `network-visibility`. New lessons never join an existing topic. The two core topics are in the six careers that rate networking 2.
- **Tests coupled to the course** now derive counts from the data; generator and career specs list the new topics on purpose.
- **Course map** (Part B): the first seeded map, so tests that count maps must scope to their course. A wide map draws narrow (D61): 184px nodes, 72px gutters, the goal as a band; keep relation labels to 10 characters and part labels short enough for their shape (pipeline stages get about 78px in the compact footprint), with the detail in the note.
- **The course page caches its API call for 60 seconds** (`serverGetBody`): after a content import, the first request serves the old page and refreshes it.
- **Browser checks of lesson pages** in headless Edge: wait by polling `page.evaluate`, because the app's CSP blocks Playwright's `waitForFunction` and some locator waits. Load at most a handful of lessons a minute as one user (the `api` limit is 120 a minute).

## F3: course map (Parts A and B, 2026-11-23)

- **Data** (D57): `course_graphs` (one map per course) and `lesson_architectures` (the published architecture; drafts and revisions carry it in the lesson snapshot under `architecture`). Lessons are named by slug, sections by UUID (by key in content files).
- **One validator:** `Content\CourseMap\MapSchemas` with `schemas/` and shared `fixtures/`; the zod mirror arrives in Part B and must agree on every fixture. Versions go through `MapVersions` (procedure in CONTENT_AUTHORING_GUIDE §4d).
- **Readers** get the map inside `GET /courses/{slug}` (`graph`), built by `CourseMap::forLearners` from the published lessons the endpoint loads: never filter it on the client. Completion comes from `/courses/{slug}/progress`.
- **Admin** (D58): `GET/PUT /admin/content/courses/{id}/graph` and `POST …/graph/restore/{audit_id}`; saved live, audited with the whole map, `lock_version`, and an admin save makes imports skip the map unless `--force`.
- **Health** (D60): `content:check-graphs`, run by `cybercompass:release` after the import as warnings and in CI through Pest.
- **Shapes:** 10, listed in `MapSchemas::SHAPES`; adding one is described in CONTENT_AUTHORING_GUIDE §4d.
- **Renderer** (Part B, DESIGN_SYSTEM "Course map"):
  - `features/course-map`: `layout.ts` holds the pure rules (placement, focus room, related and dimmed, keys); `CourseMap` draws, and the focused lesson's node grows in place (`layoutGrid` per-item size); `ExpandedLesson` (its inside) loads on first use; `ArchitectureView` is shared with the admin preview.
  - The shapes are in `lib/graph/shapes`. Keep `sizes.ts` and `glyphs.ts` free of the layouts, so the overview bundle stays small.
- **Admin panels:** `ArchitecturePanel`, inside the lesson details, and `CourseGraphPanel`, on `/admin/courses`.
- **Testing:** `cybercompass:e2e-course-map install|remove` (local and testing only) gives a test map on Applied Cryptography. Playwright installs it in global setup and removes it in teardown. After installing by hand, the public course page can show the old version for up to 60 seconds.
- **Lesson anchors:** `DocumentLayout` scrolls to `#<section uuid>` once a lesson renders (lessons load on the client).
- **Cache deadlocks:** the `database` cache driver is `App\Support\Cache\RetryingDatabaseStore` (registered in `AppServiceProvider`), which retries writes MySQL picks as deadlock victims (parallel first requests of one user racing on the rate limiter row).
- **Hallmark sloplint:** run `~/.claude/skills/hallmark/scripts/sloplint.mjs` copied into a folder with `puppeteer-core` installed, with `CHROME_PATH` set to Edge; `--scope app` and `--url` for the render tier (it cannot log in, so admin screens get the static pass). Lint component files together with `src/app/globals.css`: the focus ring (gate 26a, never waivable) is the global `:focus-visible` rule there, so a component linted alone reports "no focus-visible styling" although every control has the ring (checked by keyboard in the admin Course map and Architecture panels, 2026-11-24).

## F1: compass menu and keyboard shortcuts (formerly Phase 7.5, 2026-11-22)

- **Compass** (D51, DESIGN_SYSTEM "Compass menu"): `features/compass`, mounted by `PageShell`. Routes come only from `lib/navigation.ts`, `SETTINGS_TABS` and `ADMIN_AREAS`; a new header link, settings tab or admin area shows up in the compass with no compass change. A page adds sections with `useCompassPageItems()`.
- **Shortcuts** (D52): every shortcut is an action in `lib/shortcuts/registry.ts` used through `useShortcut()`; it then appears in Settings → Keyboard shortcuts. A new action id needs its `shortcuts.actions.*` messages in en and vi.
- **Preferences** (D53): `user_profiles.compass_enabled` and `shortcuts`, through `PATCH /me/preferences`; the server checks shape only.
- **E2E:** `e2e/compass.spec.ts` uses learner accounts 9 and 10 (`compass`, `compass2`); global setup now makes 11 learners.

## F2: blog (Part A, 2026-11-22)

- **Block documents** (D55): lessons and posts share `Content/Documents` (`BlockDocument`, `WorkingCopy`, `DocumentDrafts`, `DocumentPublisher`, `ReadingTime`) plus the generic `SectionWriter`, `BlockSerializer` (was `LessonSerializer`) and `RevisionSummary`. A third document type is four twin tables, a model implementing `BlockDocument`, a `WorkingCopy` subclass for its metadata, and an editor adapter. The D30 exercise check stays lesson-only inside `SectionWriter`.
- **Posts** live in the `Blog` module and the `posts`, `post_sections`, `post_drafts`, `post_revisions` tables. Every reader query goes through `Post::scopeVisibleToReaders`. Posts never touch progress, events, roadmaps or notifications.
- **Editor:** `DocumentEditor` with an `EditorAdapter` per type (`lesson-editor.tsx`, `post-editor.tsx`); a type's own wording is a message namespace (`blog.editor`, `blog.history`) that falls back to `content.editor` and `content.history` (`useDocText`). One document API (`documentApi`) and one set of hooks (`useEditorDoc`, `useDocActions`).
- **Reader:** `DocumentLayout` is the lesson reader's layout; `enhanced` (posts) adds heading links, lazily mounted heavy blocks, a scrolling table of contents and `ReadingProgress`. Lessons render exactly as before.
- **Content files:** `content/posts/<slug>.php`, imported by `content:import` and `cybercompass:release`; images may be named by file (D56, `ContentFileMedia`), recorded in `lesson_media.source`. `content:export <slug> --post`.
- **Public pages** `/blog` and `/blog/[slug]` are server-rendered and fetch uncached, so a publish shows at once. `SITE_URL` (server-only) gives canonical and Open Graph URLs.
- **Seeded post:** `docker-containers-101` uses every block type; `SeededPostsTest` keeps it that way. Blog tests start from an empty blog inside their transaction, and media tests count only uploads (`source` null), because seeding now adds posts and images.
- **E2E:** `e2e/blog.spec.ts`; `performance.spec.ts` publishes a 120-block post for the reader budget; the sweep covers `/blog` and the Docker post. The purge in `cybercompass:e2e-users` deletes posts made by e2e accounts.

## UI change after Part G (2026-11-22)

- **Sticky side panels** go through one utility, `lg:sticky-panel` (DESIGN_SYSTEM §5): exercise theory panel, assessment domains nav (it overflowed a 768px-tall screen), settings and admin navs, and the lab task list. Never write another `sticky top-…` string for a side column.
- **Lab workspace** fills the screen on `lg+`, so a learner reads any task and types in the console without scrolling the page. The checkpoint `<li>` carries `data-passed`, used to scroll the list to the next open task. Below `lg` nothing changed.

## Phase 6 notes that stay true

- **Activity is written only by listeners** (D33) through `Progress\Services\ActivityRecorder`; streaks and weeks use the learner's stored timezone through `LearnerClock` (legacy aliases such as Asia/Saigon accepted). Never compute progress in the UI.
- **Notifications store type, parameters and a path, never text** (D34); the app words them (`useNotificationText`) and emails use `lang/*/notifications.php`. One-off sends claim a key through `SendOnce`.
- **Charts** are `lib/charts` components with a table view each (D32); no chart library.
- **Search** goes through `App\Support\Search\Like` (D15) and `Lesson::scopeVisibleToLearners` (D36).

## Phase 5 notes that stay true

- **Graphs:** the roadmap graph and knowledge map are HTML nodes over static SVG edges laid out by `lib/graph/grid.ts` (D27). Keep the outline below 48rem.
- **Blocks size themselves by their container:** every lesson `Section` is a CSS container and block layouts use `@xl:`-style container queries, so the same renderer works in the reader, the exercise theory panel and the editor preview. Use container queries, not viewport breakpoints, in any new block.
- **Exercises are not assessment:** exercise keys stay server-side until submission; a passing attempt completes the lesson (D31); sections that teach a question cannot be deleted (D30).
- **Labs run on the server** (D28) under the ARCHITECTURE §7.1 rules. A new engine implements `LabEngine`, registers in `SimulatedLabProvider` and must pass `tests/Unit/Labs/EngineSafetyTest.php`.

## Conventions to keep

- **Learner-visible lessons only.** Every learner query, roadmaps included, goes through `Lesson::scopeVisibleToLearners`.
- **Archive, never delete** lessons and assessment questions once anything refers to them.
- **Seeded content** upserts by a stable key (slug, section UUID, question `key`) and archives what leaves the file. The assessment bank is loaded by the Assessment module (D24) from `seeders/content/assessment.php`.
- **Roadmaps change only on request** (D26). `POST /learning-path/generate` is the only way to create a version; the path reports `outdated` reasons instead.
- **Business rules stay on the server.** The UI reads the band thresholds from the API's `formula` and status from `PathProgress`; it never re-scores or recomputes status.
- **Adding an admin area** (for example, the question bank follow-up) takes three things:
  - a page under `frontend/src/app/(admin)/admin/<area>`
  - an entry in `features/admin/components/admin-nav.tsx`
  - routes behind `auth:sanctum`, `EnsureAdmin` and `throttle:admin`, with a policy per action and an audit log entry for every change ([PHASE_3_5 §8](PHASE_3_5_CONTENT_AND_ADMIN.md#8-admin-threat-notes))
- **Configuration and migrations.** Read configuration through `config()`, never `env()`. Migrations are additive only. Name indexes explicitly when the generated name would pass MySQL's 64-character limit.

## Known caveats

- **Fixed question bank:** every attempt asks the same 40 questions. The result reveals answers only for missed questions (D26); a larger, randomly drawn pool is a follow-up content task ([roadmap follow-ups](IMPLEMENTATION_ROADMAP.md#follow-ups-not-scheduled-in-a-phase)). The schema already supports it.
- **Topic links and admin-owned lessons:** `topics.php` can add an admin-owned lesson to a topic, but removing it from the file does not unlink it (D19).
- **Assessment content is English:** the title, description and questions are content (O2); the UI around them is bilingual.
- **Vitest timing under load:** long interaction tests can pass the 5-second default during a full run (`register-form.test.tsx` once; the exercise-screen test now has 15 seconds). They pass alone and in later runs.
- **3D in automated screenshots:** full-page captures in headless Edge can stall a 3D scene until the watchdog closes it. Capture at viewport size.
- **Tests coupled to seeded content:** derive expected values from the data rather than hard-coding them.
- **Export formatting:** `content:export` output can reorder payload keys (MySQL JSON); review the diff before committing.
- **3D scripts** can be switched off with `CONTENT_SCRIPTED_3D=false` ([SANDBOXED_3D.md](SANDBOXED_3D.md)).
- **Not built yet:** email verification; a sitemap (F2 adds the blog without one).
- **Compass top ring** has 12 to 14 items once signed in (F2 added Blog), still icons-only with the hub naming each.
- **Admin post list** keeps its search and filter in component state, not in the address (the lesson list uses the address).
- **Lesson time** counts only when a learner reaches the end of a lesson; reading part of one records nothing (Phase 6 known gap).
- **Notifications created in the same second** can list in either order (timestamps are per second).
- **Lab content is English** like lessons (O2); engine messages (shell errors, refusals) are English too, while the UI around them is bilingual.
- **Old browser checks** (scratchpad `*-check.mjs`, Phases 3.5 to 6) are replaced by the Playwright suite. Four `e2e-*@example.com` accounts from 2026-11-19 remain in the dev database; they are outside the purge pattern on purpose.

## Local environment

- **Run the app:**
  - PHP and MySQL run only in Docker: `docker compose up -d`.
  - Next.js runs on the host: `npm run dev` in `frontend/` (D13).
  - Commands that write files into `backend/`, such as `content:export` and Pint, need `docker compose exec -u root api …`.
- **Demo accounts:**
  - `demo@cybercompass.test` and `admin@cybercompass.test`.
  - Their passwords come from `DEMO_USER_PASSWORD` and `DEMO_ADMIN_PASSWORD` in `backend/.env`, which is not committed. The admin account is skipped when its variable is empty.
  - On first seed the demo learner gets a SOC Analyst goal, a submitted assessment (strengths, developing domains and gaps), one finished lesson and a roadmap. Re-seeding leaves a learner who already has a roadmap alone.
  - To recover admin access: `php artisan cybercompass:make-admin <email>`.
