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

## Authentication

| Method | Path | Notes |
|---|---|---|
| GET | `/sanctum/csrf-cookie` | Sets `XSRF-TOKEN`. Call it before login and register. |
| POST | `/api/v1/auth/register` | name, email, password, password_confirmation. Logs in on success. |
| POST | `/api/v1/auth/login` | email, password, remember (bool) |
| POST | `/api/v1/auth/logout` | |
| POST | `/api/v1/auth/forgot-password` | email. Always returns 202, so it never reveals whether an email exists. |
| POST | `/api/v1/auth/reset-password` | token, email, password, password_confirmation |
| GET | `/api/v1/me` | Current user with profile, goal and preferences |

## Endpoint map

🔓 public · 🔒 requires auth

### Profile and preferences

| | Method | Path |
|---|---|---|
| 🔒 | PATCH | `/me/profile` (name, phone, date_of_birth, education_level) |
| 🔒 | POST | `/me/avatar` (multipart image ≤ 2 MB, jpg/png/webp; re-encoded server-side) |
| 🔒 | PUT | `/me/learning-goal` (career_id, self_level, weekly_minutes, domain_ids[]) |
| 🔒 | PATCH | `/me/preferences` (locale, theme) |
| 🔒 | PUT | `/me/password` (current_password, password, password_confirmation) |
| 🔒 | GET/PUT | `/me/notification-preferences` |

### Taxonomy and careers

| | Method | Path |
|---|---|---|
| 🔓 | GET | `/domains` |
| 🔓 | GET | `/careers` |
| 🔓 | GET | `/careers/{slug}` (responsibilities, domains, skills, suggested courses, roadmap preview) |

### Catalog

| | Method | Path |
|---|---|---|
| 🔓 | GET | `/courses` (q, filter[domain], filter[difficulty], filter[favorited] 🔒, sort, page) |
| 🔓 | GET | `/courses/{slug}` (chapters, lessons, objectives, skills, prerequisites, related; plus `viewer` state when authenticated) |
| 🔒 | POST/DELETE | `/courses/{slug}/favorite` |
| 🔒 | POST | `/courses/{slug}/enroll` |

### Learning

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/lessons/{slug}` (sections, objectives, takeaways, resources, prerequisites, progress) |
| 🔒 | POST | `/lessons/{slug}/start` |
| 🔒 | POST | `/lessons/{slug}/complete-reading` (time_spent_seconds) |
| 🔒 | GET | `/lessons/{slug}/exercise` (questions **without answers**) |
| 🔒 | POST | `/lessons/{slug}/exercise/attempts` (answers[]), which returns per-question correctness, the correct answer, the explanation and the section reference |
| 🔒 | GET | `/exercise-attempts/{id}` |

### Assessment

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/assessments/active` |
| 🔒 | POST | `/assessments/{slug}/attempts` (start; resumes an unsubmitted attempt if one exists) |
| 🔒 | GET | `/assessment-attempts/{id}` (questions + the learner's saved answers) |
| 🔒 | PUT | `/assessment-attempts/{id}/answers/{questionId}` (option_id), which autosaves |
| 🔒 | POST | `/assessment-attempts/{id}/submit` (409 if already submitted) |
| 🔒 | GET | `/assessment-attempts/{id}/result` (overall, per-domain score + band, strengths, gaps, next steps, formula) |
| 🔒 | GET | `/assessment-attempts` (history) |

### Roadmap

| | Method | Path |
|---|---|---|
| 🔒 | GET | `/learning-path` (the active path: nodes with computed status, edges, lessons per node with status, completion %) |
| 🔒 | POST | `/learning-path/generate` (career_id optional; uses the goal and the latest attempt) |
| 🔒 | GET | `/learning-paths` (versions) |
| 🔓 | GET | `/careers/{slug}/roadmap` (template preview, with no personal status) |

The response shape is graph-ready but layout-free. The client computes positions.

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

### Search

| | Method | Path |
|---|---|---|
| 🔓 | GET | `/search?q=` (courses, lessons, careers, grouped; max 5 each) |

## Security notes

- Exercise and assessment answer keys never appear in a response before the attempt is submitted. Feature tests assert this.
- Policies scope every user-owned record. Another user's record returns 404, not 403.
- Uploads are validated by MIME type and dimensions, stored under random names, and served from S3 or CloudFront. User-supplied SVG is never served.
- Lesson content is structured JSON rendered by React components. There is no `dangerouslySetInnerHTML` with user or content HTML.
