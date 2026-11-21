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
| 🔒 | PATCH | `/me/preferences` (locale, theme, `timezone` (an IANA name, legacy aliases such as Asia/Saigon accepted, Phase 6); any of them) | ✅ Phase 2, timezone Phase 6 |
| 🔒 | PUT | `/me/password` (current_password, password, password_confirmation). Ends other sessions. | ✅ Phase 2 |
| 🔒 | PUT | `/me/learning-goal` (`career` slug, `self_level` beginner/intermediate/advanced, `weekly_minutes` 30 to 2400, `domains[]` slugs). Every field is optional and only the ones sent change; null clears. Returns me, whose `learning_goal` is `{career {slug, title} \| null, self_level, weekly_minutes, domains[]}` or null. Changing the career never rebuilds the roadmap. | ✅ Phase 4 |
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
| 🔒 | GET | `/lessons/{slug}` (typed sections, objectives, takeaways, resources, course prerequisites, previous and next lesson across chapters, my progress, and `exercise` from Phase 5). ✅ Phase 3 |
| 🔒 | POST | `/lessons/{slug}/start` (idempotent). ✅ Phase 3 |
| 🔒 | POST | `/lessons/{slug}/complete` (time_spent_seconds, capped at 4 h per call; returns the course progress). ✅ Phase 3 |
| 🔒 | GET | `/lessons/{slug}/exercise`: questions **without answers**, each with its theory section `{id (UUID), heading}`; `pass_percent`, `question_count`, `passed`, `best_score`, `attempt_count`. 404 when the lesson has no exercise or learners cannot see it. ✅ Phase 5 Part B |
| 🔒 | POST | `/lessons/{slug}/exercise/attempts` `{answers: [{question_id, response}], time_spent_seconds?}` (rate limit `exercise-attempts`, 20 per minute): 201 with the result. A question left out is unanswered and wrong; a response of the wrong shape, a question of another exercise or the same question twice is 422 and nothing is written. A passing attempt completes the lesson (D31). ✅ Phase 5 Part B |
| 🔒 | GET | `/exercise-attempts/{id}`: one of my attempts with its result; anyone else's is 404. ✅ Phase 5 Part B |

The lesson (`GET /lessons/{slug}`) carries `exercise: {question_count, pass_percent, passed}` or null, and `POST /lessons/{slug}/complete` returns 422 (`errors.exercise`) for a lesson whose exercise is not passed yet; completions recorded before the exercise existed stay. Roadmap and knowledge map lessons carry `exercise: {passed}` or null.

Question payloads and responses by type (`Learning\Services\QuestionTypes`; option, item and right-item ids are display positions, so an id never reveals an answer):

| Type | `payload` | `response` |
|---|---|---|
| `single_choice` | `{options: [{id, label}]}` | `{option: id}` |
| `scenario` | `{scenario, options}` | `{option: id}` |
| `multi_choice` | `{options}` | `{options: [id]}` (exact set) |
| `true_false` | `{}` | `{value: bool}` |
| `fill_blank` | `{text (one ___ per blank), blanks: n}` | `{blanks: [text]}` (case and spacing ignored) |
| `matching` | `{left: [{id, label}], right: [{id, label}]}` (right shuffled) | `{matches: [right id or null per left item]}` |
| `ordering` | `{items: [{id, label}]}` (shuffled, never already in order) | `{order: [id]}` |

Result shape (D31: `correct_answer` is null when the answer was right; it has the response's shape, except blanks, which list every accepted text):

```json
{ "data": {
  "id": 4, "lesson": { "slug": "osi-model", "title": "The OSI model" }, "score": 80, "passed": true, "pass_percent": 70,
  "correct": 4, "total": 5, "submitted_at": "...", "lesson_status": "completed",
  "questions": [{ "id": 17, "position": 3, "type": "fill_blank", "prompt": "...", "payload": { "text": "...", "blanks": 3 },
                  "section": { "id": "7c1e...", "heading": "Encapsulation" },
                  "response": { "blanks": ["segment", "packet", "cell"] }, "is_correct": false,
                  "correct_answer": { "blanks": [["segment"], ["packet"], ["frame"]] }, "explanation": "..." }]
} }
```

### Assessment (✅ Phase 4 Part A)

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/assessments/active`: slug, title, description, `question_count`, `domains[]` (slug, name, question_count), `formula` (the same object as in the result, so the UI never restates the band edges), `open_attempt` {id, answered, total} or null, `latest_result` {id, score, band, submitted_at} or null. 404 if none is active. |
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

### Roadmap (✅ Phase 4 Part C)

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/learning-path`: my active roadmap, or `{"data": null}` before the first one |
| 🔒 | POST | `/learning-path/generate`: no body. Builds a new version from my career goal and latest submitted assessment, archives the old one, 201. 422 (`errors.career`) without a career goal. |
| 🔒 | GET | `/learning-paths`: my versions, newest first: id, version, status, generated_at, career, assessment_score, topic_count |

Every route acts on the signed-in learner, so there is no id to address someone else's roadmap. The career template is already part of `GET /careers/{slug}` (`roadmap.stages`), so there is no separate template endpoint. The response is graph-ready but layout-free; the client computes positions (Phase 5 reuses it for the graph).

```json
{ "data": {
  "id": 7, "version": 2, "status": "active", "generated_at": "...",
  "career": { "slug": "soc-analyst", "title": "SOC Analyst" },
  "assessment": { "attempt_id": 12, "score": 53, "submitted_at": "..." },
  "completion_percent": 34,
  "estimate": { "remaining_minutes": 219, "weekly_minutes": 240, "weeks": 1 },
  "next_lesson": { "slug": "threats-vulnerabilities-risk", "title": "...", "topic": "Security principles" },
  "outdated": ["new_assessment"],
  "nodes": [{ "topic": { "slug": "linux-shell", "title": "...", "summary": "...", "level": 1, "domain": { "slug": "linux", "name": "Linux" } },
              "stage": 1, "position": 3, "recommendation": "required", "status": "in_progress", "percent": 50,
              "reason": { "key": "gap", "params": { "domain": "Linux", "score": 22 } },
              "requires": [],
              "lessons": [{ "slug": "linux-file-permissions", "title": "...", "summary": "...", "estimated_minutes": 18, "is_required": true, "status": "completed" }] }],
  "edges": [{ "from": "linux-shell", "to": "linux-investigation" }]
} }
```

`status` is `locked`, `available`, `in_progress` or `completed`; lesson `status` is `not_started`, `started` or `completed`. `reason.key` is `career` {career}, `gap` {domain, score}, `strength` {domain, score} or `foundation` {topic, domain}; the UI renders it through i18n. `outdated` lists `career_changed` and `new_assessment`.

### Knowledge map (✅ Phase 5 Part A)

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/knowledge-map`: every topic with a lesson I can see, by domain and level, with my status; needs no career goal |

Topic status uses the same rule as the roadmap (`TopicStatus`, ARCHITECTURE §6.3), except that a topic is available once every prerequisite topic on the map is completed (there is no plan, so `review` opens nothing). Domain completion counts the required lessons of the domain's topics; Phase 6 `/progress/skills` reuses `KnowledgeMap::domains()`.

```json
{ "data": {
  "domains": [{ "slug": "networking", "name": "Networking", "score": 82, "band": "strength", "completion": { "completed": 1, "total": 6, "percent": 17 } }],
  "topics": [{ "slug": "addressing-and-services", "title": "...", "summary": "...", "level": 1, "domain": "networking",
               "status": "locked", "percent": 0, "in_path": true, "requires": ["networking-basics"],
               "lessons": [{ "slug": "ip-addressing", "title": "...", "summary": "...", "estimated_minutes": 15, "is_required": true, "status": "not_started" }] }],
  "edges": [{ "from": "networking-basics", "to": "addressing-and-services" }]
} }
```

`score` and `band` are null before the first submitted assessment.

### Labs (✅ Phase 5 Part D, ARCHITECTURE §7, D28)

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/labs`: labs on offer, in order: slug, title, summary, engine, difficulty, estimated_minutes, domain, lesson (only if learners can see it), `progress` {started, completed, passed, total} |
| 🔒 | GET | `/labs/{slug}`: the summary plus `briefing` (markdown), `scenario` (only what the learner may see: never answers, hidden test packets, routes or files), `checkpoints` [{id, title, kind, hint, passed}] and `attempt` {id, version, state, completed_at} or null. 404 for an archived lab. |
| 🔒 | POST | `/labs/{slug}/attempts`: start, 201 the first time, then 200 with my existing attempt (one per learner and lab). |
| 🔒 | POST | `/lab-attempts/{id}/actions` `{version, action}` (`throttle:lab-actions`, 60 per minute; action at most 8 KB): runs one action on the server engine and returns `{output, newly_passed, checkpoints, attempt}`. 409 when `version` is not the attempt's current version (another tab moved on; nothing changes). 422 (`errors.action`) for a malformed action or when the stored state would pass 256 KB. Someone else's attempt is 404. |
| 🔒 | POST | `/lab-attempts/{id}/reset` `{version}`: starts the attempt over (same version check); completion is kept. |

Actions by engine (each engine validates its own; a refused shell command is output, not an error):

| Engine | Actions | Output |
|---|---|---|
| every engine | `{type: "answer", checkpoint, value}` (≤ 200 characters; compared ignoring case and spacing) | `{checkpoint, correct}` |
| `linux-shell` | `{type: "command", command}` (≤ 500 characters, ≤ 5 pipe stages) | `{text, error, clear?}` (≤ 64 KB) |
| `firewall-rules` | `add_rule {rule, position?}`, `remove_rule {index}`, `move_rule {from, to}`, `set_default {policy}`, `test {packet}` | `{verdict, rule}` for a test |
| `http-inspector` | `{type: "send", request: {method, path, headers?, cookies?, body?}}` | `{response: {status, headers, body}}` |

The lesson (`GET /lessons/{slug}`) lists `labs` [{slug, title}] that practise it.

### Progress

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/progress/summary`: `{enrolled_courses, completed_courses, completed_lessons, total_minutes, week {starts_on, minutes, goal_minutes}, streak {current, longest, active_today, last_active_on}, roadmap {career, completion_percent} \| null, timezone}` ✅ |
| 🔒 | GET | `/progress/activity?range=12w` (4w to 52w, else 422): `{timezone, lessons_completed_before, weeks [{starts_on, minutes, lessons_completed}]}`, oldest first, Monday weeks ✅ |
| 🔒 | GET | `/progress/skills`: the knowledge map's `domains` rows `[{slug, name, score, band, completion {completed, total, percent}}]` ✅ |
| 🔒 | GET | `/progress/recent`: the last 15 activities, lesson starts left out, `[{id, type, occurred_at, minutes, subject}]`; `subject.kind` is `lesson` {slug, title}, `lab` {slug, title}, `exercise` {lesson, score, passed} or `assessment` {id, title, score} ✅ |
| 🔒 | GET | `/progress/next`: `{source: roadmap \| courses \| null, lessons [{slug, title, topic or course, estimated_minutes}]}`; up to 3 from the active roadmap (`PathProgress::nextLessons`), else the next lesson of each unfinished enrolled course ✅ |
| 🔒 | GET | `/progress/courses`: `[{slug, title, domain, enrolled_at, completed_at, completed_lessons, total_lessons, percent}]`, unfinished first ✅ |
| 🔒 | GET | `/progress/assessments`: the last 20 submitted attempts, oldest first, in the `/assessment-attempts` summary shape ✅ |

Streaks and weeks use the learner's local date (`user_profiles.timezone`, D33; UTC until set). Phase 6 Part A.

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
