# Performance

Phase 7 Part D: what was measured, the budgets that now guard it, and what was changed. Every budget below is enforced by a test, so a regression fails CI rather than waiting for the next review.

## 1. API: queries per request

**Method.** `tests/Feature/Performance/QueryBudgetTest.php` calls 21 hot endpoints as the demo learner (a career goal, a submitted assessment, a roadmap and progress) against the real seeded content, counting the queries of a second, warm request. Strict mode (`Model::shouldBeStrict()`) already makes a lazily loaded relation throw in every test, so classic N+1 queries cannot pass; the budgets also catch queries issued inside loops.

| Endpoint | Queries (2026-11-21) | Budget |
|---|---|---|
| `GET /me` | 1 | 3 |
| `GET /progress/summary` | 25 | 30 |
| `GET /progress/activity?range=12w` | 4 | 6 |
| `GET /progress/next` | 13 | 16 |
| `GET /progress/recent` | 7 | 9 |
| `GET /progress/assessments` | 5 | 7 |
| `GET /progress/skills` | 12 | 15 |
| `GET /progress/courses` | 1 | 3 |
| `GET /notifications/unread-count` (the bell, once a minute) | 1 | 3 |
| `GET /notifications` | 2 | 4 |
| `GET /learning-path` | 13 | 16 |
| `GET /knowledge-map` | 14 | 17 |
| `GET /lessons/osi-model` | 13 | 16 |
| `GET /lessons/osi-model/exercise` | 9 | 11 |
| `GET /courses` | 3 | 5 |
| `GET /courses/networking-fundamentals` | 13 | 16 |
| `GET /careers` | 2 | 4 |
| `GET /careers/soc-analyst` | 8 | 10 |
| `GET /search?q=net` | 6 | 8 |
| `GET /labs` | 4 | 6 |
| `GET /domains` | 1 | 3 |

**Growth check.** After the learner completes six more lessons (which also writes activities, course progress, notifications and milestones through the real listeners), every endpoint runs exactly the same number of queries. Relations are loaded in batches (`where ... in (...)`), never per row.

**Accepted.** `progress/summary` reads the learner's time zone up to four times (one-row lookups by primary key). Caching it would add per-request state to `LearnerClock` for no measurable gain.

## 2. Database indexes

`EXPLAIN` on the queries that run for every learner on every dashboard load or bell poll, on the development database:

| Query | Index used | Rows examined |
|---|---|---|
| Unread notifications count, notifications page | `notifications_notifiable_type_notifiable_id_index` | 1 |
| Study time in a date range, recent activity | `learning_activities_user_id_occurred_at_index` | 1 |
| Lessons completed | `lesson_progress_user_id_lesson_id_unique` | 1 |
| Active roadmap | `learning_paths_user_id_version_unique` | 1 |
| Enrollments | `enrollments_user_id_course_id_unique` | 1 |
| Passed exercises | `exercise_attempts_user_id_exercise_id_passed_index` | 1 |
| Queue worker poll (every 3 s) | `jobs_queue_index` | 1 |
| Session clean-up | `sessions_last_activity_index` | 1 |

No full table scans. The checks were run with a temporary probe that was deleted afterwards; re-run them with `EXPLAIN` in `php artisan tinker` after a schema change.

## 3. Web: JavaScript per route

**Bundle composition** (`npx next analyze --output`, compressed client JavaScript per route, after the change in §5):

| Route | KB |
|---|---|
| `/`, `/courses` | 291 |
| `/dashboard` | 297 |
| `/roadmap` | 298 |
| `/labs/[slug]` | 340 |
| `/learn/[slug]` | 371 |
| `/learn/[slug]/exercise` | 378 |
| `/admin/lessons/[id]` | 389 |

About 205 KB of every route is Next.js and React. The rest: TanStack Query 13 KB, lucide icons 8 to 11 KB, tailwind-merge 8 KB, the message formatter 6 KB; lesson pages add zod (34 KB, block validation), dagre (15 KB, only with a flow or tree block, D23) and the block renderers (23 KB). Three.js is not in any route: it loads inside the 3D sandbox only when a 3D block scrolls near (D22).

**Page weight and API traffic** (`e2e/performance.spec.ts`, cold cache, CI's production build, JavaScript as transferred with gzip by `next start`):

| Route | JS (KB) | API requests on load | Budget (JS, API) | LCP (CI) |
|---|---|---|---|---|
| `/` | 219 | 1 | 250, 2 | 264 ms |
| `/courses` | 219 | 3 | 250, 4 | 156 ms |
| `/courses/networking-fundamentals` | 218 | 1 | 250, 2 | 164 ms |
| `/careers/soc-analyst` | 218 | 1 | 250, 2 | 168 ms |
| `/dashboard` | 224 | 9 | 260, 10 | 428 ms |
| `/roadmap` | 224 | 4 | 260, 5 | 360 ms |
| `/learn/osi-model` | 255 | 5 | 300, 6 | 432 ms |
| `/learn/osi-model/exercise` | 260 | 4 | 300, 5 | 428 ms |
| `/labs/investigate-a-suspicious-login` | 229 | 3 | 265, 4 | 380 ms |

These figures were measured before the change in §5, which takes zod off the public pages and the dashboard. CI enforces the budgets with `E2E_PERF=1`; locally (`next dev`, unminified) the spec only writes its report.

**The `api` limit (120 a minute per learner).** A dashboard load makes 9 API requests (8 panels and the unread count), and the bell then polls once a minute. A learner can reload the dashboard about 13 times a minute before reaching the limit, far beyond normal use; the performance spec asserts the dashboard stays at 14 requests or fewer.

## 4. Images and static files

- **Uploads** are re-encoded to WebP by the API (avatars 256 px square, lesson images at most 1600 px wide), so no image larger than needed is ever stored or served.
- **Lesson images** render with their width and height (no layout shift) and `loading="lazy"`. Course thumbnails reserve their space with `aspect-ratio`. The admin media picker's grid now loads lazily (§5).
- **`public/`** holds no raster images. The only large static file is the 3D runtime (`public/sandbox/scene3d/frame.js`, 935 KB, about 180 KB with brotli), loaded only by pages with a 3D block. Its name is fixed, so it is served with revalidation rather than as immutable; on AWS, CloudFront compresses it and answers revalidations from its edge (Part G).

## 5. Changes made in Part D

- **zod and the CSP.** Production's Content Security Policy (no `eval`, D42) reported zod's one-time `new Function` probe as a violation on pages with 3D blocks and in the editor (found by CI's production build, invisible in development). Every module that uses zod now imports `@/lib/zod-config`, which turns on zod's jitless mode; a unit test checks every such module. A first attempt that set it in the root providers put zod (34 KB) on every page, and a second that re-exported `z` from one module defeated tree-shaking (zod grew to 131 KB on lesson pages); the bundle analyzer caught both.
- **Admin media picker** grid images load lazily.

## 6. Re-running

| Check | Command |
|---|---|
| Query budgets and growth | `docker compose exec api php artisan test --filter=QueryBudget` |
| Page weight and API budgets | `E2E_PERF=1 npm run e2e -- e2e/performance.spec.ts` against a production build (CI does this) |
| Bundle composition | `npx next analyze` (interactive) or `npx next analyze --output --snapshot <name>` then `npx next analyze export --snapshot <name>` |
