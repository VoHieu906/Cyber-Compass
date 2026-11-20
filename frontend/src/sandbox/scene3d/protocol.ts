/*
 * Messages between the lesson page (parent), the sandboxed frame and its worker
 * (docs/SANDBOXED_3D.md §3). Every receiver checks the sender and the shape: the frame's
 * origin is "null", so `event.source` is the only trustworthy sender check.
 */

export type Theme = "light" | "dark";
export type BlockKind = "scene-3d" | "script-3d";

/** UI text the frame shows; supplied by the parent so the sandbox needs no i18n library. */
export type FrameStrings = {
  play: string;
  pause: string;
  resetView: string;
  previousStep: string;
  nextStep: string;
  stepOf: string; // "{current} / {total}"
  close: string;
  stalled: string;
  failed: string;
  unsupported: string;
  hint: string;
};

/** Parent -> frame. */
export type ParentMessage =
  | {
      type: "render";
      kind: BlockKind;
      payload: unknown;
      theme: Theme;
      reducedMotion: boolean;
      strings: FrameStrings;
      debug: boolean;
    }
  | { type: "visibility"; visible: boolean }
  | { type: "theme"; theme: Theme };

/** Frame -> parent. */
export type FrameStatus = "loading" | "running" | "error" | "stalled" | "unsupported";
export type FrameMessage = { type: "ready" } | { type: "status"; status: FrameStatus; message?: string; line?: number };

/** Frame -> worker. */
export type WorkerInit = {
  type: "init";
  kind: BlockKind;
  canvas: OffscreenCanvas;
  payload: unknown;
  width: number;
  height: number;
  pixelRatio: number;
  theme: Theme;
  reducedMotion: boolean;
};
export type ToWorker =
  | WorkerInit
  | { type: "resize"; width: number; height: number }
  | { type: "event"; event: ForwardedEvent }
  | { type: "visibility"; visible: boolean }
  | { type: "playing"; playing: boolean }
  | { type: "step"; index: number }
  | { type: "reset" }
  | { type: "theme"; theme: Theme };

/** Worker -> frame. Treated as untrusted when author code runs there. */
export type FromWorker =
  | { type: "started"; animated: boolean }
  | { type: "tick" }
  | { type: "select"; id: string | null }
  | { type: "error"; message: string; line?: number };

/** The parts of DOM input events OrbitControls and picking need, as plain data. */
export type ForwardedEvent = {
  type: "pointerdown" | "pointermove" | "pointerup" | "pointercancel" | "wheel" | "keydown" | "keyup" | "click";
  pointerId?: number;
  pointerType?: string;
  button?: number;
  buttons?: number;
  clientX?: number;
  clientY?: number;
  deltaX?: number;
  deltaY?: number;
  deltaMode?: number;
  ctrlKey?: boolean;
  metaKey?: boolean;
  shiftKey?: boolean;
  altKey?: boolean;
  key?: string;
  code?: string;
};

export const SETUP_TIMEOUT_MS = 3000;
export const STALL_TIMEOUT_MS = 3000;
export const MAX_PIXEL_RATIO = 1.5;

export function isParentMessage(data: unknown): data is ParentMessage {
  const t = (data as { type?: unknown } | null)?.type;
  return t === "render" || t === "visibility" || t === "theme";
}

export function isFrameMessage(data: unknown): data is FrameMessage {
  const m = data as { type?: unknown; status?: unknown } | null;
  if (m?.type === "ready") return true;
  return m?.type === "status" && ["loading", "running", "error", "stalled", "unsupported"].includes(String(m.status));
}
