# API Specification

The API is versioned under `/api/v1`. Requests and responses are JSON. The generated OpenAPI document (Scramble) is served at `/docs/api` in non-production environments and is the authoritative contract. This file records the conventions and the endpoint map.

## Conventions

### Request

- Headers: `Accept: application/json`. Mutating requests also send `X-XSRF-TOKEN` (see auth).
- `Accept-Language: en | vi` selects the language of validation messages and translated content.
- IDs in URLs: **slugs** for public content (`/courses/network-fundamentals`), numeric IDs for user-owned records (`/assessment-attempts/42`).
- Field names use `snake_case` on the wire. The generated TS types keep that casing, so there is no mapping layer.

### Success envelope

```json
{ "data": { ... } }
```

Collections:

```json
{
  "data": [ ... ],
  "meta":  { "current_page": 1, "per_page": 12, "total": 48, "last_page": 4 },
  "links": { "next": "...", "prev": null }
}
```

These are Laravel API Resources. Pagination is `?page=` and `?per_page=` (max 50).

### Errors

```json
{
  "message": "The given data was invalid.",
  "code": "validation_failed",
  "errors": { "email": ["The email has already been taken."] }
}
```

| Status | `code` | When |
|---|---|---|
| 400 | `bad_request` | Malformed input not covered by validation |
| 401 | `unauthenticated` | No or expired session |
| 403 | `forbidden` | A policy denied access |
| 404 | `not_found` | Missing resource, or another user's resource (no leak) |
| 409 | `conflict` | State conflict (e.g. resubmitting a submitted attempt) |
| 419 | `csrf_mismatch` | Bad or missing XSRF token |
| 422 | `validation_failed` | Form Request failure, with `errors` |
| 429 | `too_many_requests` | Rate limited, with a `Retry-After` header |
| 500 | `server_error` | Generic message only, no internals |

### Filtering and sorting

`?filter[domain]=networking&filter[difficulty]=2&q=dns&sort=-published_at`, where `sort` takes a comma-separated list of fields and `-` means descending. Each endpoint whitelists its filter and sort fields.

## Authentication (✅ Phase 2)

Passwords: 8 to 128 characters, at least one letter and one number.

| Method | Path | Notes |
|---|---|---|
| GET | `/sanctum/csrf-cookie` | Sets `XSRF-TOKEN`. Call it before login and register. |
| POST | `/api/v1/auth/register` | name, email, password, password_confirmation. Logs in on success (201). 10 per hour per IP. |
| POST | `/api/v1/auth/login` | email, password, remember (bool). 5 failures per email+IP lock it for 60 s (429 with `errors.email`). |
| POST | `/api/v1/auth/logout` | |
| POST | `/api/v1/auth/forgot-password` | email. Always returns 202, so it never reveals whether an email exists. |
| POST | `/api/v1/auth/reset-password` | token, email, password, password_confirmation. Ends every session of that user. |
| GET | `/api/v1/me` | Current user with profile, goal and preferences |

## Endpoint map

🔓 public · 🔒 requires auth

### Profile and preferences

| | Method | Path | Status |
|---|---|---|---|
| 🔒 | PATCH | `/me/profile` (name, phone, date_of_birth, education_level) | ✅ Phase 2 |
| 🔒 | POST | `/me/avatar` (multipart `avatar`: jpg/png/webp, 64–6000 px, ≤ 2 MB; stored as a 256 px WebP under a random name) | ✅ Phase 2 |
| 🔒 | DELETE | `/me/avatar` | ✅ Phase 2 |
| 🔒 | PATCH | `/me/preferences` (locale, theme; either or both) | ✅ Phase 2 |
| 🔒 | PUT | `/me/password` (current_password, password, password_confirmation). Ends other sessions. | ✅ Phase 2 |
| 🔒 | PUT | `/me/learning-goal` (career_id, self_level, weekly_minutes, domain_ids[]) | Phase 4 |
| 🔒 | GET/PUT | `/me/notification-preferences` | Phase 6 |

### Taxonomy and careers

| | Method | Path |
|---|---|---|
| 🔓 | GET | `/domains` (with published course counts). ✅ Phase 3 |
| 🔓 | GET | `/careers`. ✅ Phase 3 |
| 🔓 | GET | `/careers/{slug}` (responsibilities, domains with required level, skills, suggested courses, `roadmap.stages[].topics[]` with `requires`). ✅ Phase 3 |

### Catalog

| | Method | Path |
|---|---|---|
| 🔓 | GET | `/courses` (q, filter[domain], filter[difficulty], filter[favorited] for signed-in viewers, sort = newest, title, -title, difficulty, -difficulty, duration, -duration; page, per_page default 6). Signed-in viewers get `is_favorited`. ✅ Phase 3 |
| 🔓 | GET | `/courses/{slug}` (chapters, lessons, objectives, skills, prerequisites, up to 3 related). The same for every viewer. ✅ Phase 3 |
| 🔒 | GET | `/courses/{slug}/progress` (enrolled, favorited, completed/total lessons, percent, next lesson, status of each lesson). ✅ Phase 3 |
| 🔒 | POST/DELETE | `/courses/{slug}/favorite` (idempotent). ✅ Phase 3 |
| 🔒 | POST | `/courses/{slug}/enroll` (idempotent: 201 the first time, then 200). ✅ Phase 3 |

### Learning

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/lessons/{slug}` (typed sections, objectives, takeaways, resources, course prerequisites, previous and next lesson across chapters, my progress). ✅ Phase 3 |
| 🔒 | POST | `/lessons/{slug}/start` (idempotent). ✅ Phase 3 |
| 🔒 | POST | `/lessons/{slug}/complete` (time_spent_seconds, capped at 4 h per call; returns the course progress). ✅ Phase 3 |
| 🔒 | GET | `/lessons/{slug}/exercise` (questions **without answers**) |
| 🔒 | POST | `/lessons/{slug}/exercise/attempts` (answers[]), which returns per-question correctness, the correct answer, the explanation and the section reference |
| 🔒 | GET | `/exercise-attempts/{id}` |

### Assessment (✅ Phase 4 Part A)

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/assessments/active`: slug, title, description, `question_count`, `domains[]` (slug, name, question_count), `open_attempt` {id, answered, total} or null, `latest_result` {id, score, band, submitted_at} or null. 404 if none is active. |
| 🔒 | POST | `/assessments/{slug}/attempts`: 201 with a new attempt, or 200 with the open one (one open attempt per learner, enforced by a unique index). |
| 🔒 | GET | `/assessment-attempts/{id}`: status (`in_progress`, `submitted`), total, answered, `questions[]` (id, position, domain, difficulty, prompt, code, `options[]` {id, label}, `selected_option_id`). No answer key. |
| 🔒 | PUT | `/assessment-attempts/{id}/answers/{questionId}` (`option_id`, required key, null clears): autosave, returns `answered`. 409 once submitted; 404 for a question the attempt did not draw; 422 for an option of another question. |
| 🔒 | POST | `/assessment-attempts/{id}/submit`: scores the attempt and returns the result. 409 if already submitted. Fires `AssessmentSubmitted` after commit. |
| 🔒 | GET | `/assessment-attempts/{id}/result`: 409 while open. See the shape below. |
| 🔒 | GET | `/assessment-attempts` (`per_page` ≤ 50): submitted attempts, newest first: id, submitted_at, score, band, `domains[]` {slug, score, band}. |

Another learner's attempt answers 404 on every route (`AssessmentAttemptPolicy`).

Result shape (D26: `correct_option` and `explanation` are null when the answer was correct):

```json
{ "data": {
  "id": 12, "assessment": { "slug": "initial-knowledge", "title": "..." }, "started_at": "...", "submitted_at": "...",
  "overall": { "score": 61, "band": "developing", "earned": 44, "possible": 72, "correct": 25, "answered": 38, "total": 40 },
  "domains": [{ "domain": { "slug": "networking", "name": "Networking" }, "score": 56, "band": "developing", "earned": 5, "possible": 9, "correct": 3, "answered": 4, "total": 5 }],
  "strengths": ["linux"], "gaps": ["cryptography"],
  "next_steps": [{ "kind": "course", "reason": "gap", "domain": { "slug": "cryptography", "name": "Cryptography" }, "score": 22, "course": { "slug": "applied-cryptography", "title": "..." } }],
  "formula": { "weights": { "1": 1, "2": 2, "3": 3 }, "unanswered": "incorrect", "developing_from": 40, "strength_from": 75 },
  "review": [{ "question_id": 3, "position": 3, "domain": "networking", "difficulty": 2, "prompt": "...", "code": null, "is_correct": false,
               "your_option": { "id": 9, "label": "..." }, "correct_option": { "id": 8, "label": "..." }, "explanation": "..." }]
} }
```

### Roadmap

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/learning-path` (the active path: nodes with computed status, edges, lessons per node with status, completion %) |
| 🔒 | POST | `/learning-path/generate` (career_id optional; uses the goal and the latest attempt) |
| 🔒 | GET | `/learning-paths` (versions) |

The career template is already part of `GET /careers/{slug}` (`roadmap.stages`), so there is no separate template endpoint. The response shape is graph-ready but layout-free. The client computes positions.

```json
{ "data": {
  "id": 7, "version": 2, "career": { "slug": "soc-analyst", "title": "SOC Analyst" },
  "completion_percent": 34,
  "nodes": [{ "topic": { "slug": "networking-fundamentals", "title": "...", "domain": "networking" },
              "stage": 1, "recommendation": "required", "status": "in_progress",
              "reason": { "key": "gap", "params": { "domain": "networking", "score": 31 } },
              "lessons": [{ "slug": "osi-model", "title": "...", "status": "completed", "is_required": true }] }],
  "edges": [{ "from": "networking-fundamentals", "to": "tcp-ip-deep-dive" }]
} }
```

### Labs

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/labs`, `/labs/{slug}` (scenario without the solution) |
| 🔒 | POST | `/labs/{slug}/attempts` (actions[]), which is validated server-side and returns the checkpoints passed |

### Progress

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/progress/summary` (enrolled, completed courses, completed lessons, total minutes, streak, roadmap %) |
| 🔒 | GET | `/progress/activity?range=12w` (weekly minutes, lessons completed per week) |
| 🔒 | GET | `/progress/skills` (latest domain scores + completion per domain) |
| 🔒 | GET | `/progress/recent` (activity feed) |
| 🔒 | GET | `/progress/next` (up to 3 recommended next lessons from the roadmap) |

### Notifications

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/notifications?filter[unread]=1` |
| 🔒 | GET | `/notifications/unread-count` |
| 🔒 | POST | `/notifications/{id}/read` |
| 🔒 | POST | `/notifications/read-all` |
| 🔒 | DELETE | `/notifications/{id}` |

### Admin (✅ Phase 3.5 Part A)

🛡 = `auth:sanctum` + `EnsureAdmin` + the `admin` rate limiter (120 per minute per admin). Guests get 401, learners 403 (`forbidden`). Every mutating call writes an audit log entry. Full design: [PHASE_3_5_CONTENT_AND_ADMIN.md](PHASE_3_5_CONTENT_AND_ADMIN.md) §5.1.

| | Method | Path | |
|---|---|---|---|
| 🛡 | GET | `/admin/overview` (user counts: total, admins, suspended, new and signed in over 7 days; lessons by status; last 5 audit entries) | ✅ |
| 🛡 | GET | `/admin/users` (q on name and email, filter[role] = learner, admin; filter[status] = active, suspended; sort = name, -name, created_at, -created_at (default), last_login_at, -last_login_at; page, per_page default 20, max 50). Phone and date of birth are never included. | ✅ |
| 🛡 | GET | `/admin/users/{id}` (role, status, suspension {at, reason}, `is_last_active_admin`, stats {enrollments, completed_lessons}, last 10 audit entries about the user) | ✅ |
| 🛡 | PATCH | `/admin/users/{id}/role` (role). Ends the user's sessions. 422 `role` for self-demotion or the last active admin. Same role: no-op, no audit entry. | ✅ |
| 🛡 | POST | `/admin/users/{id}/suspend` (reason, 5 to 300 chars, admin-only). Ends sessions and rotates the remember token. 422 `user` for your own account, the last active admin, or an already suspended account. | ✅ |
| 🛡 | POST | `/admin/users/{id}/reactivate`. 422 `user` if not suspended. | ✅ |
| 🛡 | POST | `/admin/users/{id}/password-reset`. 202; sends the standard reset email to the account's own address. 429 if one was sent in the last minute, 422 for a suspended account. | ✅ |
| 🛡 | GET | `/admin/audit-logs` (filter[actor], filter[subject_type] = user, lesson, chapter; filter[subject_id], filter[action]; newest first; per_page default 25) | ✅ |
| 🛡 | GET | `/admin/content/block-types` (key, label, description, editor `form`/`json`/`scene`/`script`, JSON Schema, example; `diagram_keys` lists the bespoke diagrams offered for new blocks; the legacy `osi-model` and `tcp-handshake` still validate but are not listed) | ✅ B |
| 🛡 | GET | `/admin/content/courses` (every course, chapter and lesson in order, any status, with `has_unpublished_changes`) | ✅ B |
| 🛡 | POST | `/admin/content/courses/{id}/chapters` (title; added at the end) | ✅ B |
| 🛡 | PATCH | `/admin/content/chapters/{id}` (title) | ✅ B |
| 🛡 | PUT | `/admin/content/courses/{id}/chapters/order` (chapter_ids: every chapter once) · `/admin/content/chapters/{id}/lessons/order` (lesson_ids: every lesson once). Applied at once; reordered lessons become admin-owned, so imports keep the order. 422 if the set differs. | ✅ B |
| 🛡 | GET | `/admin/content/lessons` (q on title/slug, filter[course], filter[chapter], filter[status], filter[has_draft]; sort -updated_at (default), course, title; section_count, visual_count, last edited by/at) | ✅ B |
| 🛡 | POST | `/admin/content/lessons` (chapter_id, title, slug). 201; a `draft` lesson at the end of the chapter, invisible to learners | ✅ B |
| 🛡 | GET | `/admin/content/lessons/{id}` (`working_copy` = draft if any else live, `preview_sections` serialized exactly like the learner endpoint, `lock_version` (0 without a draft), `has_unpublished_changes`, `slug_editable`, `can_delete`, topics) | ✅ B |
| 🛡 | PUT | `/admin/content/lessons/{id}/draft` (whole working copy + lock_version). Sections without `id` get a UUID; ids of other lessons or duplicates → 422; stale lock → 409 `conflict`. Errors keyed like the body: `sections.3.payload.items.0`, in plain words | ✅ B |
| 🛡 | DELETE | `/admin/content/lessons/{id}/draft`. 422 for a never-published lesson (delete it instead) | ✅ B |
| 🛡 | POST | `/admin/content/lessons/{id}/publish` (lock_version, note?). Re-validates; needs a summary and ≥1 section; writes revision n+1; a chapter change appends at the end of the new chapter; republishes archived lessons | ✅ B |
| 🛡 | POST | `/admin/content/lessons/{id}/archive` (published only) | ✅ B |
| 🛡 | DELETE | `/admin/content/lessons/{id}`. 204 only if never published and no progress or topic links; else 409 | ✅ B |
| 🛡 | GET | `/admin/content/lessons/{id}/revisions` (version, author, note, section count, `changes` {initial, fields, sections {added, removed, edited, reordered}}) | ✅ B |
| 🛡 | POST | `/admin/content/lessons/{id}/revisions/{version}/restore` (lock_version). Into the draft only | ✅ B |
| 🛡 | GET/POST | `/admin/content/media` (list, 24 per page) · upload: multipart `image` jpg/png/webp 64–6000 px ≤ 5 MB + `alt` (3–250, required); re-encoded to WebP ≤ 1600 px wide; SVG rejected; `uploads` rate limit | ✅ B |

Content routes are exempt from the `TrimStrings` and `ConvertEmptyStringsToNull` middleware: content is stored as written (code indentation survives), and each block type's normalizer trims what should be trimmed. Draft saves are not audited one by one; create, publish, discard, archive, delete, restore, chapter and order changes, and uploads are. `EnsureAdmin` runs before route model binding, so learners get 403 whether or not an id exists.

Audit entry shape: `{ id, action, actor: {id, name} | null, subject: {type, id, label}, changes, created_at }`. `actor` is null for the `cybercompass:make-admin` command.

**Login change:** a suspended account gets 422 with `errors.email = auth.suspended`, only after the correct password; a wrong password still gets `auth.failed`. Successful logins set `last_login_at`. Any request from a session that belongs to a suspended user is answered 401.

**Lesson change (additive):** `GET /lessons/{slug}` section `id`s are now stable UUIDs (were `section-{position}`). Draft and archived lessons answer 404 and are left out of every count, curriculum and roadmap.

### Search

| | Method | Path |
|---|---|---|
| 🔓 | GET | `/search?q=` (courses, lessons, careers, grouped; max 5 each). Phase 6; the catalog has its own search today. |

## Security notes

- Exercise and assessment answer keys never appear in a response before the attempt is submitted. Feature tests assert this.
- Policies scope every user-owned record. Another user's record returns 404, not 403. Admin routes are the exception by design: a learner gets 403 on every `/admin/*` route (AdminAccessTest checks all of them, read from the router).
- Admins never see password hashes, remember tokens or reset tokens, and the audit log strips them from `changes`.
- Phase 6 global search must use `Lesson::scopeVisibleToLearners`, so drafts never leak through it.
- Uploads are validated by MIME type and dimensions, stored under random names, and served from S3 or CloudFront. User-supplied SVG is never served.
- Lesson content is structured JSON rendered by React components. There is no `dangerouslySetInnerHTML` with user or content HTML.
