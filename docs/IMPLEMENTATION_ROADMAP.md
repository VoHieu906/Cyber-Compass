# Implementation Roadmap

Seven phases. Each one ends with the same gate: tests pass, `tsc --noEmit` is clean, Laravel has no errors, the UI has been checked in a real browser at 1280px and 390px in both themes, the docs are updated, and a report lists what was done and what remains.

```mermaid
flowchart LR
  P1[1 Foundation] --> P2[2 Auth + profile]
  P2 --> P3[3 Content + catalog + landing]
  P3 --> P4[4 Assessment + personalization]
  P4 --> P5[5 Roadmap UI, exercises, labs]
  P5 --> P6[6 Dashboard + notifications]
  P6 --> P7[7 Quality + deployment]
```

## Phase 1: Foundation

- Monorepo skeleton, `.gitignore`, `.env.example`, git init
- `frontend/`: Next.js (App Router, TS strict), Tailwind v4, shadcn/ui init, tokens.css + theme mapping, next-themes, next-intl (en/vi), fonts, ESLint/Prettier, Vitest + Testing Library
- `backend/`: Laravel 12, Sanctum, CORS, module folder structure, error envelope, `/api/v1/health`, Scramble, Pest
- `docker-compose.yml`: mysql 8, mailpit, api, worker, web
- Global layouts: marketing header and footer, auth layout, app shell. Theme and language switchers working.
- README foundation

**Exit:** `docker compose up` gives a web page that calls `/api/v1/health` and shows the DB status. Theme and language switch and persist. Lint, type checks and tests are green.

## Phase 2: Auth and user management

Register, login (remember me), logout, forgot and reset password (Mailpit locally), protected routes, profile tabs (personal info, avatar upload, security, preferences), notification-preference defaults.
**Tests:** auth feature tests (happy path, validation, throttling, CSRF), profile policy tests, form component tests.

## Phase 3: Learning content and the landing page

Migrations and seeders for domains, skills, careers, topics, courses, chapters, lessons and sections. Course catalog (search, filters, sort, favorites, pagination), course detail, enrollment, lesson reader with section renderers (prose, callout, steps, code, SVG diagrams), reading completion. Career catalog and detail. **Landing page** built with Hallmark from the real data.
**Content budget:** 8 domains, 7 careers, 6 courses with full lessons (about 30 lessons), the rest as published outlines.

## Phase 4: Assessment and personalization

Assessment (about 40 questions), autosaving attempt flow, scoring service, result page with domain bands and the formula, career goal selection, the roadmap generator and versioning.
**Tests:** scoring unit tests (weights, bands, unanswered questions), answer keys never leak, generator rules, single active path.

## Phase 5: Interactive learning

Roadmap graph (React Flow + dagre): expand and collapse, statuses, prerequisites, lesson drawer, mobile outline view. Knowledge map by domain. Exercise mode in all seven question types, split-screen and mobile tabs, feedback with theory references, completion rules. Labs: `linux-shell`, `firewall-rules`, `http-inspector` engines with server checkpoint validation. One 3D visual (packet traversal across a topology), lazy-loaded.

## Phase 6: Progress and engagement

Activity recording through events, streak calculator (local-date aware), dashboard summary, weekly time, assessment history, skill-by-domain chart, recent activity, next lessons. Notifications (list, read, read-all, delete, preferences). Scheduled reminder job. Header bell with unread count.

## Phase 7: Quality and deployment

Critical-path E2E tests (Playwright: register, assess, roadmap, lesson, exercise, dashboard). Security review (OWASP checklist, dependency audit, headers, rate limits). Performance (bundle analysis, image sizes, N+1 checks). Responsive and accessibility audit. Hallmark sweep. Production Dockerfiles, ECS task definitions, step-by-step AWS deployment guide.

## Decisions made

| # | Decision | Outcome (2026-11-19) |
|---|---|---|
| O1 | Local toolchain | **Docker Desktop.** PHP, Composer, MySQL and Mailpit run in containers. The host needs only Node and Docker. |
| O2 | Learning content language | **English content.** The UI is bilingual (en/vi). Translatable content columns can be added later without changing the API shape. |

## Open decisions
| O3 | **Production domain layout.** Sanctum needs the frontend and API on the same site. | `app.<domain>` and `api.<domain>` behind one ALB | AWS docs, CORS config |
| O4 | **Email verification required before learning?** | Not required in the MVP; verification email sent, banner shown | Auth flow |
