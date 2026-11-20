import {
  isParentMessage,
  SETUP_TIMEOUT_MS,
  STALL_TIMEOUT_MS,
  type ForwardedEvent,
  type FrameMessage,
  type FrameStrings,
  type FromWorker,
  type ParentMessage,
  type ToWorker,
} from "./protocol";
import { sceneSchema, scriptSchema, type Scene3d } from "./schema";

/*
 * The sandboxed frame's own code (docs/SANDBOXED_3D.md §3). Runs in an opaque-origin
 * document; author code never runs here, only in the worker this file starts. UI text is
 * set with textContent only. Messages are accepted from the parent window and our worker,
 * and both are checked.
 */

declare const __WORKER_SOURCE__: string;

type RenderMessage = Extract<ParentMessage, { type: "render" }>;

const toParent = (message: FrameMessage) => window.parent.postMessage(message, "*"); // the parent checks event.source

let worker: Worker | null = null;
let workerUrl: string | null = null;
let current: RenderMessage | null = null;
let scene: Scene3d | null = null;
let strings: FrameStrings | null = null;
let started = false;
let startedAt = 0;
let lastTick = 0;
let visible = true;
let playing = true;
let stepIndex = 0;

// ---------- DOM (built once, text set with textContent) ----------

function el<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  className: string,
  parent?: HTMLElement,
): HTMLElementTagNameMap[K] {
  const node = document.createElement(tag);
  node.className = className;
  parent?.appendChild(node);
  return node;
}

const root = el("div", "root", document.body);
const stage = el("div", "stage", root);
stage.tabIndex = 0;
let canvas = el("canvas", "canvas", stage);
const hint = el("p", "hint", stage);
const toolbar = el("div", "toolbar", stage);
const playButton = el("button", "tool", toolbar);
const resetButton = el("button", "tool", toolbar);
const info = el("div", "info", stage);
const infoTitle = el("h2", "info-title", info);
const infoText = el("p", "info-text", info);
const infoClose = el("button", "info-close", info);
const notice = el("p", "notice", stage);
const steps = el("div", "steps", root);
const prevButton = el("button", "step-button", steps);
const stepBody = el("div", "step-body", steps);
const stepCount = el("p", "step-count", stepBody);
const stepTitle = el("h2", "step-title", stepBody);
const stepText = el("p", "step-text", stepBody);
const nextButton = el("button", "step-button", steps);
for (const b of [playButton, resetButton, infoClose, prevButton, nextButton]) b.type = "button";
prevButton.textContent = "‹";
nextButton.textContent = "›";
infoClose.textContent = "×";
hideAll();

function hideAll() {
  for (const node of [hint, toolbar, playButton, info, notice, steps]) node.hidden = true;
}

// ---------- Parent messages ----------

window.addEventListener("message", (event) => {
  if (event.source !== window.parent || !isParentMessage(event.data)) return;
  const message = event.data;
  if (message.type === "render") render(message);
  if (message.type === "visibility") {
    visible = message.visible;
    lastTick = performance.now(); // a hidden worker doesn't count as stalled
    post({ type: "visibility", visible: visible && document.visibilityState === "visible" });
  }
  if (message.type === "theme" && current) render({ ...current, theme: message.theme });
});

document.addEventListener("visibilitychange", () => {
  lastTick = performance.now();
  post({ type: "visibility", visible: visible && document.visibilityState === "visible" });
});

toParent({ type: "ready" });

// ---------- Rendering ----------

function render(message: RenderMessage) {
  stop();
  hideAll();
  current = message;
  strings = message.strings;
  document.documentElement.dataset.theme = message.theme === "dark" ? "dark" : "light";
  stage.setAttribute("aria-label", strings.hint);

  const parsed =
    message.kind === "scene-3d" ? sceneSchema.safeParse(message.payload) : scriptSchema.safeParse(message.payload);
  if (!parsed.success) {
    const issue = parsed.error.issues[0];
    return report("error", `${issue?.path.join(".") || "payload"}: ${issue?.message ?? "invalid"}`);
  }
  scene = message.kind === "scene-3d" ? (parsed.data as Scene3d) : null;

  if (typeof OffscreenCanvas === "undefined" || !("transferControlToOffscreen" in HTMLCanvasElement.prototype)) {
    return report("unsupported", strings.unsupported);
  }

  // A canvas can hand over control only once, so each render gets a fresh one.
  const fresh = el("canvas", "canvas");
  stage.replaceChild(fresh, canvas);
  canvas = fresh;
  wireInput(canvas);
  const offscreen = canvas.transferControlToOffscreen();

  workerUrl = URL.createObjectURL(new Blob([__WORKER_SOURCE__], { type: "text/javascript" }));
  worker = new Worker(workerUrl);
  worker.onmessage = (e: MessageEvent<FromWorker>) => onWorkerMessage(e.data);
  worker.onerror = (e) => {
    e.preventDefault();
    fail(e.message || "Worker error");
  };

  const rect = stage.getBoundingClientRect();
  started = false;
  startedAt = performance.now();
  playing = !message.reducedMotion;
  stepIndex = 0;
  report("loading");
  post(
    {
      type: "init",
      kind: message.kind,
      canvas: offscreen,
      payload: parsed.data,
      width: Math.max(1, Math.round(rect.width)),
      height: Math.max(1, Math.round(rect.height)),
      pixelRatio: window.devicePixelRatio || 1,
      theme: message.theme,
      reducedMotion: message.reducedMotion,
    },
    [offscreen],
  );
}

function post(message: ToWorker, transfer: Transferable[] = []) {
  worker?.postMessage(message, transfer);
}

function stop() {
  worker?.terminate();
  worker = null;
  if (workerUrl) URL.revokeObjectURL(workerUrl);
  workerUrl = null;
}

function report(status: "loading" | "running" | "error" | "stalled" | "unsupported", message?: string, line?: number) {
  if (status === "error" || status === "stalled" || status === "unsupported") {
    stop();
    hideAll();
    notice.textContent =
      current?.debug && message
        ? message + (line ? ` (line ${line})` : "")
        : ((status === "stalled"
            ? strings?.stalled
            : status === "unsupported"
              ? strings?.unsupported
              : strings?.failed) ?? "");
    notice.hidden = false;
  }
  toParent({
    type: "status",
    status,
    message: current?.debug ? message : undefined,
    line: current?.debug ? line : undefined,
  });
}

function fail(message: string, line?: number) {
  report("error", message, line);
}

/** Worker messages are untrusted when author code runs there: check type and look up any text ourselves. */
function onWorkerMessage(data: unknown) {
  const message = data as Partial<FromWorker> | null;
  if (!message || typeof message.type !== "string") return;
  if (message.type === "tick") {
    lastTick = performance.now();
    return;
  }
  if (message.type === "started" && !started) {
    started = true;
    lastTick = performance.now();
    showControls(Boolean((message as { animated?: unknown }).animated));
    report("running");
    return;
  }
  if (message.type === "select") {
    const id = typeof message.id === "string" ? message.id : null;
    const object = id && scene ? scene.objects.find((o) => o.id === id && o.info) : null;
    if (!object?.info) {
      info.hidden = true;
      return;
    }
    infoTitle.textContent = object.info.title;
    infoText.textContent = object.info.text;
    info.hidden = false;
    return;
  }
  if (message.type === "error") {
    const line =
      typeof message.line === "number" && Number.isFinite(message.line) ? Math.round(message.line) : undefined;
    fail(String(message.message ?? "Error").slice(0, 300), line);
  }
}

// The watchdog: setup must finish in time, and a visible scene must keep sending heartbeats.
setInterval(() => {
  if (!worker) return;
  const now = performance.now();
  if (!started && now - startedAt > SETUP_TIMEOUT_MS) return report("stalled", "Setup took longer than 3 seconds.");
  if (started && visible && document.visibilityState === "visible" && now - lastTick > STALL_TIMEOUT_MS) {
    report("stalled", "Stopped responding for 3 seconds.");
  }
}, 500);

// ---------- Controls ----------

function showControls(animated: boolean) {
  if (!strings) return;
  hint.textContent = strings.hint;
  hint.hidden = false;
  toolbar.hidden = false;
  playButton.hidden = !animated;
  setPlaying(playing);
  resetButton.textContent = strings.resetView;
  resetButton.setAttribute("aria-label", strings.resetView);
  prevButton.setAttribute("aria-label", strings.previousStep);
  nextButton.setAttribute("aria-label", strings.nextStep);
  infoClose.setAttribute("aria-label", strings.close);
  steps.hidden = !(scene?.steps && scene.steps.length > 0);
  if (!steps.hidden) showStep(0);
}

function setPlaying(value: boolean) {
  playing = value;
  playButton.textContent = value ? strings!.pause : strings!.play;
  playButton.setAttribute("aria-pressed", String(!value));
  post({ type: "playing", playing: value });
}

function showStep(index: number) {
  const list = scene?.steps ?? [];
  if (list.length === 0) return;
  stepIndex = Math.max(0, Math.min(index, list.length - 1));
  const step = list[stepIndex];
  stepCount.textContent = strings!.stepOf
    .replace("{current}", String(stepIndex + 1))
    .replace("{total}", String(list.length));
  stepTitle.textContent = step.title;
  stepText.textContent = step.text;
  prevButton.disabled = stepIndex === 0;
  nextButton.disabled = stepIndex === list.length - 1;
  info.hidden = true;
  post({ type: "step", index: stepIndex });
}

playButton.addEventListener("click", () => setPlaying(!playing));
resetButton.addEventListener("click", () => {
  post({ type: "reset" });
  if (scene?.steps?.length) showStep(stepIndex);
});
infoClose.addEventListener("click", () => (info.hidden = true));
prevButton.addEventListener("click", () => showStep(stepIndex - 1));
nextButton.addEventListener("click", () => showStep(stepIndex + 1));
root.addEventListener("keydown", (e) => {
  if (e.key === "ArrowRight" && !steps.hidden) showStep(stepIndex + 1);
  if (e.key === "ArrowLeft" && !steps.hidden) showStep(stepIndex - 1);
  if (e.key === "Escape") info.hidden = true;
});

// ---------- Input forwarding ----------

function forward(e: PointerEvent | WheelEvent, type: ForwardedEvent["type"]) {
  const rect = canvas.getBoundingClientRect();
  const pointer = e as PointerEvent;
  post({
    type: "event",
    event: {
      type,
      pointerId: pointer.pointerId,
      pointerType: pointer.pointerType,
      button: e.button,
      buttons: e.buttons,
      clientX: e.clientX - rect.left,
      clientY: e.clientY - rect.top,
      deltaX: (e as WheelEvent).deltaX,
      deltaY: (e as WheelEvent).deltaY,
      deltaMode: (e as WheelEvent).deltaMode,
      ctrlKey: e.ctrlKey,
      metaKey: e.metaKey,
      shiftKey: e.shiftKey,
      altKey: e.altKey,
    },
  });
}

function wireInput(target: HTMLCanvasElement) {
  target.addEventListener("pointerdown", (e) => {
    target.setPointerCapture(e.pointerId);
    hint.hidden = true;
    forward(e, "pointerdown");
  });
  target.addEventListener("pointermove", (e) => forward(e, "pointermove"));
  target.addEventListener("pointerup", (e) => {
    forward(e, "pointerup");
    forward(e, "click");
  });
  target.addEventListener("pointercancel", (e) => forward(e, "pointercancel"));
  target.addEventListener(
    "wheel",
    (e) => {
      e.preventDefault();
      hint.hidden = true;
      forward(e, "wheel");
    },
    { passive: false },
  );
  target.addEventListener("contextmenu", (e) => e.preventDefault());
}

new ResizeObserver(() => {
  const rect = stage.getBoundingClientRect();
  post({ type: "resize", width: Math.max(1, Math.round(rect.width)), height: Math.max(1, Math.round(rect.height)) });
}).observe(stage);
