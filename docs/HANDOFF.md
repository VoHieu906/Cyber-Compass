# Handoff

Where the project stands and what the next phase needs to know. This page only points to the docs that hold the detail. Start with the reading order in the root [CLAUDE.md](../CLAUDE.md).

## Current state (2026-11-21)

- **Phases 1 to 4 are complete** and pushed to `main`. Phase-by-phase results: [IMPLEMENTATION_ROADMAP.md](IMPLEMENTATION_ROADMAP.md).
- **Phase 5 in progress** ([plan and part reports](IMPLEMENTATION_ROADMAP.md#phase-5-interactive-learning), decisions D27 to D31). Parts A (roadmap graph, lesson drawer, knowledge map) and B (exercises backend and content) are done; next is Part C (the exercise UI).
- **Tests at handoff:** 474 Pest and 288 Vitest. Pint, tsc, ESLint and Hallmark sloplint are clean, and the production build passes.

## Read first

| Topic | Doc |
|---|---|
| Session rules, precedence, what is already delivered | [CLAUDE.md](../CLAUDE.md) |
| Architecture and every decision (D1 to D31) | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Assessment scoring and roadmap rules | [ARCHITECTURE §6](ARCHITECTURE.md#6-personalization-engine) |
| API contract | [API_SPECIFICATION.md](API_SPECIFICATION.md) |
| Schema | [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) |
| Content platform and admin console design (C1 to C21) | [PHASE_3_5_CONTENT_AND_ADMIN.md](PHASE_3_5_CONTENT_AND_ADMIN.md) |
| Sandboxed 3D: threat model and limits | [SANDBOXED_3D.md](SANDBOXED_3D.md) |
| Writing lessons, import and export | [CONTENT_AUTHORING_GUIDE.md](CONTENT_AUTHORING_GUIDE.md) |
| UI system | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) |
| Backend commands and module rules | [backend/CLAUDE.md](../backend/CLAUDE.md) |
| Next.js version notes | [frontend/AGENTS.md](../frontend/AGENTS.md) |

## What Phase 5 builds on

- **Roadmap graph:** `GET /learning-path` already returns everything a graph needs: nodes with computed status, percent, reason and `requires`, plus `edges`, lessons per node and the next lesson. Render it as HTML nodes over static SVG edges (D27, no React Flow), with stage-based layout kept separate from rendering and `layoutGraph()` only for coordinates when needed. Keep the Phase 4 outline (`features/roadmap/components/roadmap-view.tsx`) as the phone view (D25), and reuse `RoadmapNodeCard`.
- **Status lives in one place:** `Roadmap\Services\PathProgress` (ARCHITECTURE §6.3). When exercises change the completion rule, the change goes into lesson completion (`RecordLessonProgress`); node status follows automatically.
- **Exercises are not assessment:** exercise answer keys stay server-side until submission, like the assessment's. Lesson practice checks (`knowledge-check`) stay ungraded. Exercise theory references point at section UUIDs (D20).
- **3D:** the packet-traversal visual is already delivered as a `scene-3d` lesson block. New 3D is lesson content on the D22 sandbox, not new runtime code.
- **Labs** follow ARCHITECTURE §7: browser engines with server-side checkpoint validation.

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
- **Not built yet:** email verification; learning activities, notifications and global search (Phase 6). `AssessmentSubmitted` is dispatched after commit with no listeners yet; Phase 6 attaches the activity and notification listeners.

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
