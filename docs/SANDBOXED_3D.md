# Interactive 3D lesson blocks

Decision record for the Phase 3.5 request: let admins build interactive 3D content (Three.js) inside the lesson editor, with live preview, without exposing the platform or its learners to the code they write. Recorded as ARCHITECTURE **D22**. It replaces change C4 in [PHASE_3_5_CONTENT_AND_ADMIN.md](PHASE_3_5_CONTENT_AND_ADMIN.md), which had deferred 3D to Phase 5.

## 1. What is at stake

The lesson page runs on the app origin. Any script that runs there can act as the signed-in user:
- It can call the API: the session cookie is sent automatically, and the CSRF token is in a readable cookie.
- When the reader is an admin, it can change roles, publish content and suspend accounts.

So **authored JavaScript must never run on the app origin**, not even "sanitized": JavaScript can't be sanitized into safety.

## 2. Options considered

| # | Option | Creative freedom | Security | Performance isolation | Verdict |
|---|---|---|---|---|---|
| A | Run author JS in the lesson page | Full | **None.** Account takeover. | None | Rejected |
| B | Sanitize or whitelist JS, then run it in the page | Full | Not achievable: there are endless ways to reach `fetch`, `document`, `eval` | None | Rejected |
| C | `<iframe sandbox="allow-scripts">` running author code on the frame's own thread | Full | The frame gets an opaque origin, so no cookies, storage or API access. But it can still navigate itself to any URL with data in it (phishing, beacons), and draw a fake login form. | **Weak.** An infinite loop freezes the whole lesson tab in browsers that don't give sandboxed frames their own process. Nothing can interrupt it. | Rejected on its own |
| D | C, plus: author code runs only in a **Web Worker** inside the sandbox, drawing to an **OffscreenCanvas**, under a **CSP with no network** | Full Three.js | No DOM, so no fake forms and no navigation. No network, no cookies, no storage, no app origin. | **Strong.** The worker can be terminated, and a watchdog kills one that stops answering. Rendering runs off the page's main thread. | **Chosen for code** |
| E | A declarative scene format (JSON) drawn by our own Three.js code | Wide but bounded: primitives, labels, links with moving packets, animations, click-for-info, guided steps, camera | Data only, validated by JSON Schema like every other block (D18) | Budgets are enforced at validation time | **Chosen as the default** |
| F | Serve the sandbox from a separate hostname (for example `sandbox.<domain>` on the existing CloudFront) | Same as D | Defence in depth on top of D: a different site also gets its own browser process | Better | Documented as **optional AWS hardening**. D already gives an opaque origin. |

**Decision: two block types sharing one sandboxed runtime.**
- **`scene-3d`** (option E) is the default. Admins describe a scene as structured JSON, edited with a live preview. Nothing executes except our renderer. It covers what lessons most often need:
  - objects and labelled parts
  - network links with packets flowing along them
  - spinning, bobbing and pulsing animations
  - click-to-explain
  - step-by-step guided views that move the camera and highlight parts
- **`script-3d`** (option D) is for the cases a scene can't express. The admin writes Three.js code against a small, documented API. It runs only inside the sandboxed worker. An operator can switch it off (§6).

Both render through **the same** sandbox in the editor preview and in the learner's lesson, so what an admin previews is what learners get.

## 3. Architecture

```text
Lesson page (app origin, holds the session)
 └─ <iframe sandbox="allow-scripts" src="/sandbox/scene3d/frame.html" referrerpolicy="no-referrer">
     │   opaque origin "null"; HTTP CSP: default-src 'none'; script-src 'self' 'unsafe-eval';
     │   worker-src blob:; connect-src 'none'; img-src blob: data:; style-src 'self';
     │   form-action 'none'; base-uri 'none'; frame-ancestors 'self'; sandbox allow-scripts
     │
     ├─ frame.js (our code only): validates the payload again, draws the controls (play/pause,
     │   reset view, steps, info panel) with textContent only, forwards pointer, wheel and key
     │   input, runs the watchdog
     │
     └─ Worker (blob URL, inherits the CSP): Three.js + OffscreenCanvas
          ├─ scene-3d  → our scene builder (data only)
          └─ script-3d → author code via new Function(api); network and worker APIs removed first
```

**Messages.**
- The parent and the frame talk only through `postMessage`. Each side checks `event.source`; the frame's origin is `"null"`, so the source is the only reliable check.
- The parent accepts four messages, each validated: `ready`, `status`, `error` (text only) and `select`.
- The parent sends `render` (the payload and the UI strings), `visibility` and `theme`.
- The parent never evaluates anything it receives. Text is always rendered as text.

**Build.** `frontend/src/sandbox/scene3d/*.ts` is bundled by `scripts/build-sandbox.mjs` (Vite, already a dev dependency) into `public/sandbox/scene3d/frame.js`. The worker source, with Three.js, is embedded in it. Three.js is pinned (0.186.1) and self-hosted: no CDN is involved, so there's no supply-chain risk at runtime. The app bundle doesn't grow: Three.js is only ever loaded inside the sandbox.

## 4. Threat model

Verified in a real browser (headless Edge) before building: from inside the sandbox, cookies, `localStorage`, `fetch` to the API (with credentials), `fetch` to external sites, `importScripts` and image beacons are all blocked, while the worker, `eval` and WebGL2 on an OffscreenCanvas work. These checks are kept as an automated browser test (`scene3d-sandbox-check.mjs`).

| Threat | Control |
|---|---|
| Steal the session, call the API as the viewer | The code never runs on the app origin. The sandbox origin is opaque: no cookies, no storage, and requests to the API carry no session and fail CORS. |
| XSS into the lesson page | The parent only reads typed messages and renders text with React. The frame builds its UI with `textContent`. The worker has no DOM. |
| Exfiltration or tracking beacons (fetch, XHR, WebSocket, images, `importScripts`) | CSP `connect-src 'none'`, `img-src blob: data:`, `script-src 'self'`, inherited by the blob worker. Those APIs are also deleted from the worker's scope and prototype chain before author code runs, but only as defence in depth: the CSP is the boundary, and the browser check confirms it holds even if code finds a way back to `fetch`. |
| Phishing (fake login UI, navigating the frame, popups, forms) | Author code has no DOM and no `location`: it can only draw pixels, and what it draws can't send anything anywhere. Without `allow-top-navigation`, `allow-popups` or `allow-forms`, the frame can't navigate the lesson page, open windows or submit forms. |
| Spoofed messages | The parent checks `event.source === iframe.contentWindow` and validates each message type. The frame accepts messages only from the parent window and from its own worker. Worker messages are checked as well: a `select` id must exist in the validated scene, and info text always comes from the payload, never from the message. |
| Infinite loops, heavy CPU | Everything runs in the worker. Setup must finish within 3 s, and frames must keep arriving (a heartbeat at least every 3 s while visible). Otherwise the worker is terminated and the block shows its text description. |
| Fork bombs | `Worker` and `SharedWorker` are removed from the worker scope before author code runs. Dedicated workers end with their owner anyway. |
| GPU overload | Pixel ratio capped at 1.5. Rendering pauses when the block is off screen or the tab is hidden. Declarative budgets: at most 150 objects, 150 links, 8 lights, 20 steps and 5 flowing packets per link. Losing the WebGL context ends the scene cleanly. |
| Memory exhaustion | **Residual risk** (§7). Termination frees memory, but a huge allocation can crash the frame before the watchdog acts. Only the 3D block is affected. |
| Malicious or broken stored content | `scene-3d` is validated by JSON Schema plus cross-reference checks (ids, parents, links, steps), and again by zod in the frame. `script-3d` is limited to 20 KB. Saves are audited and every publish is a revision, so harmful content can be traced and rolled back. |
| Clickjacking the console, or the sandbox opened directly | `frame-ancestors 'self'`, and the CSP `sandbox` directive forces the sandbox even when the page is opened as a top-level page. |
| Abuse by an admin or a stolen admin account | Admins already control lesson content. `script-3d` adds no access beyond the sandbox above. Operators can turn it off with `CONTENT_SCRIPTED_3D=false` (§6). |

## 5. Performance

- The iframe mounts only when the block comes within 200 px of the viewport. Rendering pauses off screen and in hidden tabs (the parent sends `visibility`).
- One runtime file, cached by the browser, is shared by every block.
- Rendering runs in a worker, so a heavy scene can't make the lesson page stutter.
- The pixel ratio is capped at 1.5 and frames are paced by the worker's `requestAnimationFrame`.
- When the reader prefers reduced motion, animations start paused, with a play button.

## 6. Operations

`CONTENT_SCRIPTED_3D` (default `true`), read through `config('cybercompass.content.scripted_3d')`. When it is `false`:
- New or edited `script-3d` blocks are refused on save.
- Learners get the block's text description instead of the code: the serializer withholds the code.
- `scene-3d` keeps working, since it is data.

## 7. Residual risks and limits (accepted)

- **Memory**, described above.
- **Content judgement.** Code can draw anything, including something misleading or offensive. That's the same trust already placed in admins who write lesson text; audit and revisions apply.
- **Browser support.** It needs OffscreenCanvas with WebGL in a worker: current Chrome, Edge, Firefox and Safari 17+. Elsewhere, the block shows its required text description.
- **Accessibility.** A canvas is not accessible by itself. Every 3D block therefore has a required text description, which screen readers announce and anyone can expand. Steps and info panels are real HTML with keyboard support.
- **Maintenance.** `script-3d` code is written against the pinned Three.js version. Upgrading Three.js means re-checking the scripted blocks; the lesson list counts them as visuals, so they are easy to find.

## 8. Authoring experience

- **`scene-3d`:**
  - Form fields for the title, the text description, the caption and the aspect ratio, plus a JSON editor for the scene itself.
  - A live preview beside them that redraws as you type, once the JSON is valid.
  - Field-level errors from the shared schema.
  - An inline reference of every shape and option.
- **`script-3d`:**
  - The same fields, plus a code editor (tab inserts spaces).
  - **Run** (and run-on-pause) re-renders the preview.
  - Errors show their line number.
  - An inline API reference: `THREE`, `scene`, `camera`, `controls`, `onFrame(fn)`, `label(text, options)`, `reducedMotion`, `theme`.
- The Preview tab and the published lesson render through the same component and runtime.

## 9. Verification (2026-11-20)

- `scene3d-sandbox-check.mjs` (headless Edge, from a real app page), all passed:
  - Inside `script-3d` code, `fetch`, `XMLHttpRequest`, `WebSocket`, `Worker`, `importScripts`, `document`, `localStorage` and `indexedDB` were all undefined, and `fetch` could not be recovered from the prototype chain.
  - An endless loop in setup was shut down at about 3 s; one inside `onFrame` was shut down after the scene had started.
  - A scene burning 40 ms of CPU per frame left the lesson page at a worst frame gap of 7 ms.
  - The host page's storage and URL were untouched, and the sandbox page carries its CSP even when opened directly.
- `scene3d-editor-check.mjs`, also all passed: live previews of both types in the editor, a dangling link flagged before saving, a syntax error reported, publish, the learner view (`sandbox="allow-scripts"`, titled and described), phone width without overflow, and no console errors.
- Pest: fixtures, cross-references, budgets, exact code storage, and the kill switch. Vitest: zod and the JSON Schemas agree on all 17 fixtures, plus step logic.
- Runtime size: 836 KB minified, 241 KB gzip, 180 KB brotli. It is fetched once, only when a 3D block is near the viewport.

## 10. Plan change for Part C

Part C becomes three gated steps:
- **C1:** this document, the sandbox runtime, `scene-3d` and `script-3d`, editor support and tests.
- **C2 ✅:** the 2D generic blocks (`layer-stack`, `sequence`, `flow`, `tree`, `topology`, `comparison`, `timeline`, `bitfield`, `calculator`, `step-through`, `knowledge-check`, extended prose), zod contract tests for every block, and OSI and TCP moved to generic blocks.
- **C3 ✅:** the pilot courses rewritten (using 3D where it earns its place), `content:export`, and the content authoring guide.
