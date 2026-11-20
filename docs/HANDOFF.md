# Handoff

Where the project stands and what the next phase needs to know. This page only points to the docs that hold the detail.

## Current state (2026-11-21)

- **Phases 1 to 3.5 are complete** and pushed to `main`. Phase-by-phase results: [IMPLEMENTATION_ROADMAP.md](IMPLEMENTATION_ROADMAP.md).
- **Next: Phase 4**, assessment and personalization ([roadmap](IMPLEMENTATION_ROADMAP.md#phase-4-assessment-and-personalization)).
- **Tests at handoff:** 337 Pest and 262 Vitest. Pint, tsc, ESLint and Hallmark sloplint are clean, and the production build passes.

## Read first

| Topic | Doc |
|---|---|
| Architecture and every decision (D1 to D23) | [ARCHITECTURE.md](ARCHITECTURE.md) |
| API contract | [API_SPECIFICATION.md](API_SPECIFICATION.md) |
| Schema | [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) |
| Content platform and admin console design, and every change made to the brief (C1 to C21) | [PHASE_3_5_CONTENT_AND_ADMIN.md](PHASE_3_5_CONTENT_AND_ADMIN.md) |
| Sandboxed 3D: threat model and limits | [SANDBOXED_3D.md](SANDBOXED_3D.md) |
| Writing lessons, import and export | [CONTENT_AUTHORING_GUIDE.md](CONTENT_AUTHORING_GUIDE.md) |
| UI system | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) |
| Backend commands and module rules | [backend/CLAUDE.md](../backend/CLAUDE.md) |
| Next.js version notes | [frontend/AGENTS.md](../frontend/AGENTS.md) |

## Phase 3.5 conventions Phase 4 depends on

The full table is in [PHASE_3_5 §7](PHASE_3_5_CONTENT_AND_ADMIN.md#7-impact-on-later-phases). The rules Phase 4 must not break:

- **Learner-visible lessons only.** Every learner-facing query, including the roadmap generator, goes through `Lesson::scopeVisibleToLearners` (or `scopePublished`). Drafts and archived lessons must never enter a roadmap.
- **Lessons are archived, never deleted**, once anything refers to them. Roadmaps can safely point at lesson ids.
- **Section UUIDs are stable** (D20) and safe to reference. In content files they come from the lesson slug and the section heading, so renaming a heading gives the section a new UUID.
- **Practice checks are not assessment.** `knowledge-check` answers ship with the lesson (PHASE_3_5 A4). Phase 4 assessment answer keys stay server-side and are never serialized before submission ([backend/CLAUDE.md](../backend/CLAUDE.md)). Don't reuse practice questions as assessment items.
- **Adding an admin area** (for example, the question bank) takes three things:
  - a page under `frontend/src/app/(admin)/admin/<area>`
  - an entry in `features/admin/components/admin-nav.tsx`
  - routes behind `auth:sanctum`, `EnsureAdmin` and `throttle:admin`, with a policy per action and an audit log entry for every change ([PHASE_3_5 §8](PHASE_3_5_CONTENT_AND_ADMIN.md#8-admin-threat-notes))
- **Content ownership** (D19). Content files seed lessons. Once a lesson is edited in the admin console, the database owns it and imports skip it; `content:export` writes it back to its file. Topic links (`database/seeders/content/topics.php`), which a roadmap generator is likely to use, are synced only for seed-owned lessons.
- **Configuration and migrations.** Read configuration through `config()`, never `env()`. Migrations are additive only.

## Known caveats

- **3D in automated screenshots:** full-page captures in headless Edge can stall a 3D scene until the watchdog closes it. Capture at viewport size. Details are in the [roadmap's C3 entry](IMPLEMENTATION_ROADMAP.md#part-c3-pilot-content--completed-2026-11-21).
- **Tests coupled to seeded content:** some tests read the seeded courses, which content changes affect. Derive expected values from the data, as the catalog duration test now does, rather than hard-coding them.
- **Export formatting:** `content:export` output can list payload keys in a different order from the original file, because MySQL stores JSON keys in its own order. The content is identical; review the diff before committing.
- **3D scripts** can be switched off with `CONTENT_SCRIPTED_3D=false`. The residual risks, chiefly memory use inside the sandbox, are listed in [SANDBOXED_3D.md](SANDBOXED_3D.md).
- **Not built yet:** email verification; learning activities and global search are planned for Phase 6.

## Local environment

- **Run the app:**
  - PHP and MySQL run only in Docker: `docker compose up -d`.
  - Next.js runs on the host: `npm run dev` in `frontend/` (D13).
  - Commands that write files into `backend/`, such as `content:export` and Pint, need `docker compose exec -u root api …`.
- **Demo accounts:**
  - `demo@cybercompass.test` and `admin@cybercompass.test`.
  - Their passwords come from `DEMO_USER_PASSWORD` and `DEMO_ADMIN_PASSWORD` in `backend/.env`, which is not committed. The admin account is skipped when its variable is empty.
  - To recover admin access: `php artisan cybercompass:make-admin <email>`.
