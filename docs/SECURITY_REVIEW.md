# Security review

Phase 7 review of every phase, not only recent work: the API (Part B) and the web app, response headers, the Content Security Policy, the 3D sandbox and the client-address handling of the deployment layout (Part C, D41, D42).

**Method.** Every route was read from the router; each area was checked against the code and against an existing or new automated test, which is cited as evidence. The checklist follows the OWASP Top 10 (2021). Findings are fixed in the same part and pinned by a test, or recorded as accepted residual risks with a reason.

**Re-run.** `docker compose exec api php artisan test` and `npm run e2e` in `frontend/` (all evidence below is in the suites; browser-side checks are in `e2e/security.spec.ts`), `docker compose exec api composer audit`, `npm audit` in `frontend/`, and `php artisan cybercompass:preflight` against a production configuration.

## 1. Findings

| ID | Severity | Area | Finding | Status | Evidence |
|---|---|---|---|---|---|
| S9 | **High** | Deployment layout (Phase 1) | `trustProxies(at: '*')` trusts every address in Laravel 13 (`0.0.0.0/0`), so `$request->ip()` was the left-most `X-Forwarded-For` entry, which the client writes. Any client could choose its own address and escape every per-address limit (login, register, password reset, search, guest API), and `X-Forwarded-Host` was trusted too | **Fixed:** trust only the immediate peer (`REMOTE_ADDR`: the ALB, the only way in under D40) and only the headers the ALB sets (`For`, `Proto`, `Port`) | `Security/ClientAddressTest` (forged prefixes still limited, clients kept apart, forged host ignored, HTTPS detected) |
| S10 | Medium | Web app (all phases) | No Content Security Policy, and no framing, sniffing or referrer headers outside `/sandbox`; the API sent no policy of its own | **Fixed (D42):** a nonce-based CSP per request in `proxy.ts` (scripts only with the nonce, `object-src 'none'`, `frame-ancestors 'none'`), with `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy` and HSTS on every page; the API adds a deny-all CSP and `Cross-Origin-Resource-Policy` to the image's nginx headers | `csp.test.ts`, `SecurityHeadersTest`, E2E `security.spec.ts` |
| S8 | Medium | Deployment layout (D41) | On AWS every server-side read by the Next.js server (public pages) comes from the web task's one address, so requests for random course or career addresses could use up that address's 120-a-minute `api` limit and break public pages for everyone | **Fixed:** the web server sends a server-only shared secret (`X-Internal-Token`, `INTERNAL_API_TOKEN`); guest reads carrying it skip the per-address limit. Abuse of public pages is limited at the edge (CloudFront, the D50 rate rule). Preflight requires the token in production | `Security/InternalTokenTest`, `PreflightCommandTest` |
| S11 | Low | Auth (Phase 2) | The reset token is in the address of `/reset-password`, and the page had no referrer policy | **Fixed:** `Referrer-Policy: no-referrer` on that page; every other page sends only its origin to other sites | E2E `security.spec.ts` |
| S1 | Medium | Auth (Phase 2) | Login was limited per email and address (5 failures, then a minute's lockout), but one address could try a common password against about 120 accounts a minute (password spraying) | **Fixed:** `throttle:login`, 20 attempts a minute per address, on top of the per-account lockout | `SessionTest`: "limits one address trying many accounts" |
| S2 | Medium | Deployment config | Nothing stopped an unsafe production configuration: session cookies are Secure only when `SESSION_SECURE_COOKIE` is set, and debug mode, a wildcard CORS list, mail sent to the log or uploads on the container disk would all start without complaint | **Fixed:** `php artisan cybercompass:preflight` checks 17 settings and fails on any unsafe one; the migration task runs it before every deployment (Part F, Part G) | `PreflightCommandTest` (a safe configuration, 16 unsafe ones, outside production) |
| S3 | Low | Auth | bcrypt reads only the first 72 bytes of a password, and the rule allowed 128 characters (up to 512 bytes), so two long passwords sharing their first 72 bytes would both be accepted | **Fixed:** `MaxBytes(72)` added to `Password::defaults()` (register, reset, change), with messages in English and Vietnamese | `RegistrationTest`: "over 72 bytes" |
| S4 | Low | All routes | No check stopped a future route from shipping without authentication or its limiter | **Fixed:** a router-driven inventory test: every API route is either on an explicit public list or behind `auth:sanctum`, every admin route has `EnsureAdmin` and `throttle:admin`, eleven routes keep their own limiter, and a guest gets 401 on every protected route | `Security/RouteInventoryTest` (346 assertions over 85 routes) |
| S5 | Low | Auth, notifications | Email addresses are not verified (O4). Someone could register with another person's address and opt in to email notifications | **Accepted** for the MVP: email is opt-in per type (D34), reminders are at most one a day, registration is limited to 10 an hour per address, and every email says how to turn it off. Follow-up: verify the address before any opt-in email is sent | D34, `NotificationsTest` |
| S6 | Info | Auth | Guessing one account's password from many addresses is limited per address, not per account | **Accepted:** a per-account lock would let anyone lock a learner out. Mitigated by the password rules, the 200 ms login timebox and the per-address limits; the D50 WAF trial adds a rate rule at the edge | `SessionTest`, D50 |
| S7 | Info | Dependencies | `composer audit` and `npm audit` (production and development dependencies) | **Clean** on 2026-11-21: no advisories | Re-run above |

## 2. Checklist by area

### Authentication and sessions (Phase 2)

| Check | Result | Evidence |
|---|---|---|
| Passwords hashed with bcrypt (12 rounds), never serialized | Pass | `User` `#[Hidden]`; `UserManagementTest`: "never exposes passwords, tokens" |
| Password rules: 8 to 128 characters and at most 72 bytes, letters and numbers, confirmed | Pass (S3) | `RegistrationTest` |
| Login failure says nothing about which part was wrong, and takes the same time for unknown emails (`SessionGuard::validate` is timeboxed to 200 ms) | Pass | `SessionTest`: "answers an unknown email exactly like a wrong password" |
| Brute force: 5 failures per email and address lock for 60 s; 20 attempts a minute per address | Pass (S1) | `SessionTest` |
| Session fixation: the session id is regenerated on login; logout invalidates it and rotates the CSRF token | Pass | `AttemptLogin`, `SessionTest`: "logs out and ends the session" |
| A password change ends other sessions; a reset ends all of them | Pass | `ProfileTest`, `PasswordResetTest` |
| Reset tokens expire after 60 minutes and can be requested once a minute per email; the answer is the same for unknown emails | Pass | `config/auth.php`, `PasswordResetTest` |
| Suspended accounts are refused at login (told only after the right password) and signed out mid-session | Pass | `UserManagementTest` |
| Role cannot be self-assigned or changed through the profile | Pass | `RegistrationTest`, `ProfileTest` |
| Cookie flags in production (Secure, HttpOnly, SameSite, domain) | Pass (S2) | `PreflightCommandTest` |

### Authorization and access control (all phases)

| Check | Result | Evidence |
|---|---|---|
| Every non-public route requires a session; the public list is explicit | Pass (S4) | `RouteInventoryTest` |
| Another learner's record answers 404, never 403 (attempts, exercise attempts, lab attempts, notifications, paths) | Pass | `AssessmentAttemptTest`, `ExerciseTest`, `LabTest`, `NotificationsTest`, `LearningPathTest` |
| Admin console: 401 for guests and 403 for learners on every admin route (checked before route binding, so ids do not leak), a policy per action, 120 requests a minute per admin | Pass | `AdminAccessTest`, `RouteInventoryTest` |
| Admin safety: no self-demotion or self-suspension, the last active admin is protected, role changes and suspensions end the user's sessions | Pass | `UserManagementTest` |
| Every admin change writes an append-only audit row with secrets removed (`password`, `token`, `secret` and related keys) | Pass | `AuditLogger::REDACTED`, `UserManagementTest`, `LessonEditorTest` |
| Learners never see drafts or archived lessons (reader, roadmap, search, recommendations) | Pass | `LessonVisibilityTest`, `SearchTest`, `RemindersAndRecommendationsTest` |
| API documentation is closed outside local development | Pass | `PreflightCommandTest`: "keeps the API documentation closed in production" |

### Answer keys and scoring (Phases 4 and 5)

| Check | Result | Evidence |
|---|---|---|
| Assessment: no key before submission; afterwards only for wrong or unanswered questions (D26) | Pass | `AssessmentAttemptTest` |
| Exercises: no key before submission; scoring only on the server; the lesson completes only through a passing attempt (D31) | Pass | `ExerciseTest` |
| Labs: checkpoint answers, hidden packets, routes and files never leave the server | Pass | `LabTest`: "never sends answers, hidden packets, routes or files" |
| Practice checks (`knowledge-check`) are ungraded, answered in the browser, and never count toward completion | Pass | No API route receives a practice answer (`RouteInventoryTest` lists them all); completion is decided only in `RecordLessonProgress` |

### Lab engines (Phase 5, ARCHITECTURE §7.1)

| Rule | Result | Evidence |
|---|---|---|
| No process, file system, network or dynamic-code call in any engine (token scan) | Pass | `EngineSafetyTest` (4 tests, including one proving the scan catches a forbidden call) |
| Shell metacharacters (`;`, `&&`, `\|\|`, `&`, redirects, backticks, `$()`, `${}`, `find -exec`) refused with a message | Pass | `LinuxShellEngineTest`; E2E `labs.spec.ts` |
| ReDoS: `grep -E` length-capped and run under lowered PCRE limits with the JIT off | Pass | `LinuxShellEngineTest`: "runs -E patterns within the length and backtracking limits"; E2E answers in under 5 s |
| Bounded work: command 500 characters, 5 pipe stages, output 64 KB, state 256 KB, request 8 KB | Pass | `LinuxShellEngineTest`, `LabTest`: "refuses an action once the stored state would pass its cap" |
| 60 actions a minute per learner | Pass | `LabTest`: "limits actions per learner per minute" |
| Stale state version answers 409 and changes nothing | Pass | `LabTest`; E2E second tab |
| HTTP inspector makes no outbound request | Pass | `FirewallAndHttpEnginesTest`; no HTTP client call anywhere in `app/` |

### Input handling, injection and uploads (all phases)

| Check | Result | Evidence |
|---|---|---|
| SQL injection: every query uses the query builder or bindings; the seven raw fragments use constants or bound values | Pass | Code search (`DB::raw`, `*Raw`): `AdminOverviewController`, `UserDirectory`, `CourseCatalog`, `CourseStructure`, `ContentImporter`, `SectionWriter`, `GlobalSearch` |
| Search: `%` and `_` are plain characters; 2 to 100 characters; 60 a minute | Pass | `SearchTest` |
| Mass assignment: models list fillable fields; `role` is not fillable | Pass | `User`, `RegistrationTest` |
| Lesson content is validated against one JSON Schema per block before it is stored; prose links must be https | Pass | `BlockRegistry` fixtures (Pest and Vitest), `LessonEditorTest` |
| Uploads: raster images only (SVG refused), size (2 MB avatars, 5 MB lesson images) and 64 to 6000 px checked before decoding, re-encoded to WebP so only pixels survive, random file names, 10 a minute | Pass | `ProfileTest`, `LessonMediaTest`. The largest allowed image decodes to about 144 MB, within the 256 MB PHP memory limit |
| No deserialization, `eval` or process execution anywhere in `app/` | Pass | Code search |
| Errors use one envelope; no stack traces or messages from exceptions when debug is off | Pass | `ErrorEnvelopeTest`: "never leaks internals on server errors when debug is off" |

### Notifications and search (Phase 6)

| Check | Result | Evidence |
|---|---|---|
| Notifications store type, parameters and a path, never text; another learner's answers 404 | Pass | `NotificationsTest` |
| Email only for types the learner opted into; nothing for suspended accounts | Pass | `NotificationsTest` (and S5) |
| Search open to guests with its own limiter; drafts, archived lessons and unpublished courses or careers never appear | Pass | `SearchTest` |

### Web app, headers and the 3D sandbox (Part C)

| Check | Result | Evidence |
|---|---|---|
| Content Security Policy on every page: a fresh nonce per response, scripts only with it (`'strict-dynamic'`, no `'unsafe-inline'`, `'unsafe-eval'` only for `next dev`), `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'none'`, network only to the app and the API, images also from the media origin; `upgrade-insecure-requests` when the API is on HTTPS | Pass | `csp.test.ts`; E2E: "pages carry a nonce CSP" |
| Inline style attributes stay allowed (`style-src 'unsafe-inline'`): graphs, charts and the editor position elements with them, and a nonce cannot cover an attribute. Script injection stays blocked | Accepted | `lib/security/csp.ts` |
| The policy blocks nothing the app needs: public pages, dashboard, roadmap and a 3D lesson in both themes, and the admin editor's live 3D preview; the whole suite passes under it (its console guard fails on any violation) | Pass | E2E `security.spec.ts`, every spec |
| Rollback without a new image: `CSP_REPORT_ONLY=true` sends the same policy as report-only | Pass | `proxy.ts` |
| Framing, sniffing, referrer, permissions, opener isolation and HSTS headers on every page except `/sandbox` | Pass | E2E `security.spec.ts` |
| API responses: deny-all CSP and `Cross-Origin-Resource-Policy` from Laravel (errors and unknown routes included); `X-Frame-Options`, `nosniff`, `Referrer-Policy` and HSTS from the image's nginx (`server-opts.d/security.conf`), each sent once | Pass | `SecurityHeadersTest`; response headers checked live |
| 3D sandbox (D22): `<iframe sandbox="allow-scripts">`, an opaque origin and its own CSP (`connect-src 'none'`); scene code cannot fetch, open a WebSocket, read storage or cookies, or reach the page | Pass | E2E: "scene code has no network, storage, cookies or access to the page" |
| No raw HTML rendering anywhere (`dangerouslySetInnerHTML` is not used); lesson links render only for https and open with `noopener noreferrer nofollow` | Pass | Code search; `prose.tsx` |
| Login's `next` accepts only same-site paths (no open redirect) | Pass | `redirect.test.ts`; E2E: "an open redirect through next is ignored" |
| Session tokens never reach JavaScript: the Sanctum session cookie is HttpOnly; `XSRF-TOKEN` is the only readable cookie, by design | Pass | ARCHITECTURE §4, D1 |
| Server-only secrets (`INTERNAL_API_TOKEN`, `CSP_*`) are never `NEXT_PUBLIC_`, so they are not built into the browser bundle | Pass | `lib/api/server.ts` (`server-only`), `proxy.ts` |

### Rate limits

| Limiter | Limit | Key |
|---|---|---|
| `api` (every API route) | 120 a minute | user, else address |
| `login` | 20 a minute | address (plus 5 failures per email and address in `AttemptLogin`) |
| `register` | 10 an hour | address |
| `password` (forgot, reset, change) | 5 a minute | user or guest, and address |
| `admin` | 120 a minute | admin |
| `uploads` | 10 a minute | user |
| `lab-actions` | 60 a minute | user |
| `exercise-attempts` | 20 a minute | user |
| `search` | 60 a minute | user, else address |

One dashboard load makes 8 requests and the bell polls once a minute, so normal use stays far below the `api` limit; Part D measures it. Per-address limits use the address the load balancer adds (S9; D41 keeps the API off CloudFront, so that is the real client). The Next.js server's own reads skip the per-address limit with its shared secret (S8).

## 3. OWASP Top 10 (2021) summary

| Category | Where it is addressed |
|---|---|
| A01 Broken access control | §2 authorization; `RouteInventoryTest` |
| A02 Cryptographic failures | bcrypt with the 72-byte rule; Secure cookies and HTTPS checked by preflight; TLS at the ALB and CloudFront (D41) |
| A03 Injection | Query builder and bindings, schema-validated content, literal search, the lab engines' refusals; the CSP against script injection (S10) |
| A04 Insecure design | Server-side scoring and labs (D28, D31), keys never sent early, explicit roadmap updates (D26) |
| A05 Security misconfiguration | `cybercompass:preflight` (S2); API docs closed; trusted proxies (S9); headers and CSP (S10) |
| A06 Vulnerable components | `composer audit`, `npm audit` clean; CI runs on every push (D46) |
| A07 Identification and authentication failures | Lockout, per-address limit (S1), timebox, session regeneration, reset rules |
| A08 Software and data integrity | Lockfiles committed and installed with `npm ci` and `composer install`; CI on every change; images built from them (Part F) |
| A09 Logging and monitoring | Append-only admin audit log; CloudWatch logs and alarms (D48, Part H2) |
| A10 Server-side request forgery | No outbound request is made from learner input (lab HTTP inspector is simulated) |
