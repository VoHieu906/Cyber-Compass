# Handoff

Where the project stands and what the next phase needs to know. This page only points to the docs that hold the detail. Start with the reading order in the root [CLAUDE.md](../CLAUDE.md).

## Current state (2026-11-21)

- **Phases 1 to 5 are complete** and pushed to `main`. Phase-by-phase results: [IMPLEMENTATION_ROADMAP.md](IMPLEMENTATION_ROADMAP.md).
- **Phase 6 in progress** ([roadmap](IMPLEMENTATION_ROADMAP.md#phase-6-progress-and-engagement)): Part A (activity recording, streaks, `/progress/*`) is done; next is Part B, the dashboard.
- **Tests at handoff:** 595 Pest and 303 Vitest. Pint, tsc, ESLint and Hallmark sloplint are clean, and the production build passes.

## Read first

| Topic | Doc |
|---|---|
| Session rules, precedence, what is already delivered | [CLAUDE.md](../CLAUDE.md) |
| Architecture and every decision (D1 to D36) | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Assessment scoring and roadmap rules | [ARCHITECTURE §6](ARCHITECTURE.md#6-personalization-engine) |
| API contract | [API_SPECIFICATION.md](API_SPECIFICATION.md) |
| Schema | [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) |
| Content platform and admin console design (C1 to C21) | [PHASE_3_5_CONTENT_AND_ADMIN.md](PHASE_3_5_CONTENT_AND_ADMIN.md) |
| Sandboxed 3D: threat model and limits | [SANDBOXED_3D.md](SANDBOXED_3D.md) |
| Writing lessons, import and export | [CONTENT_AUTHORING_GUIDE.md](CONTENT_AUTHORING_GUIDE.md) |
| UI system | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) |
| Backend commands and module rules | [backend/CLAUDE.md](../backend/CLAUDE.md) |
| Next.js version notes | [frontend/AGENTS.md](../frontend/AGENTS.md) |

## What Phase 6 builds on

- **Events to listen to:** `AssessmentSubmitted`, `ExerciseSubmitted` and `LabCompleted` are dispatched after commit with no listeners yet. Lesson completion has no event: add `LessonCompleted` in `Learning\Actions\RecordLessonProgress` (the one place lessons complete, including through a passing exercise) rather than recording activity in controllers.
- **Study time already recorded:** `lesson_progress.time_spent_seconds` (capped at 4 hours per visit), `exercise_attempts.time_spent_seconds`, and `lab_attempts.action_count` for lab activity.
- **Dashboard pieces that exist:** roadmap completion, the time estimate and the next lesson come from `Roadmap\Services\PathProgress` (`present()`, `nextLesson()`); skills by domain (latest score, band, completion) come from `Roadmap\Services\KnowledgeMap::domains()`; assessment history from `GET /assessment-attempts`; lab progress from `GET /labs`. Reuse them for `/progress/*`; never recompute status or scores.
- **Charts:** Recharts is not installed yet. Weigh it against the D27 reasoning (static SVG from our own components) when the dashboard is designed.
- **Notifications** arrive as another settings tab beside personal, learning goals, security and preferences.

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
- **Vitest timing flake:** `register-form.test.tsx` once exceeded the 5-second default timeout during a full run under load; it passes alone and in later runs.
- **3D in automated screenshots:** full-page captures in headless Edge can stall a 3D scene until the watchdog closes it. Capture at viewport size.
- **Tests coupled to seeded content:** derive expected values from the data rather than hard-coding them.
- **Export formatting:** `content:export` output can reorder payload keys (MySQL JSON); review the diff before committing.
- **3D scripts** can be switched off with `CONTENT_SCRIPTED_3D=false` ([SANDBOXED_3D.md](SANDBOXED_3D.md)).
- **Not built yet:** email verification; learning activities, notifications and global search (Phase 6).
- **Lab content is English** like lessons (O2); engine messages (shell errors, refusals) are English too, while the UI around them is bilingual.
- **Browser checks in the session scratchpad** (`graph-check.mjs`, `exercise-c-check.mjs`, `labs-check.mjs`) register `assess-check-*@example.test` learners; delete them afterwards. `exercise-c-check.mjs` reads the answer key through a temporary `backend/storage/app/exercise-key.php`, which must be deleted after the run.

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
