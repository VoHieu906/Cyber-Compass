import * as THREE from "three";
import { OrbitControls } from "three/examples/jsm/controls/OrbitControls.js";
import { buildScene, makeLabel, type BuiltScene, type StepCamera } from "./build-scene";
import {
  MAX_PIXEL_RATIO,
  type ForwardedEvent,
  type FromWorker,
  type Theme,
  type ToWorker,
  type WorkerInit,
} from "./protocol";
import { sceneSchema, scriptSchema } from "./schema";

/*
 * The sandbox worker (docs/SANDBOXED_3D.md). It owns the WebGL context on an OffscreenCanvas
 * and is the only place author code (script-3d) ever runs. It lives inside an opaque-origin
 * sandboxed frame under a CSP with no network, so the code here has no cookies, storage, DOM
 * or network to reach. The frame terminates this worker if it stops answering.
 */

type Scope = {
  postMessage: (message: FromWorker) => void;
  onmessage: ((event: MessageEvent<ToWorker>) => void) | null;
  requestAnimationFrame?: (cb: (t: number) => void) => number;
};
const scope = self as unknown as Scope;
const send = (message: FromWorker) => scope.postMessage(message);

/**
 * The minimum of a DOM element OrbitControls needs, backed by events the frame forwards.
 * getRootNode/ownerDocument return the proxy itself, which is how three.js supports workers.
 */
class ElementProxy extends THREE.EventDispatcher<
  Record<string, ForwardedEvent & { preventDefault(): void; stopPropagation(): void }>
> {
  style: Record<string, string> = {};
  clientWidth = 1;
  clientHeight = 1;
  getRootNode() {
    return this;
  }
  get ownerDocument() {
    return this;
  }
  getBoundingClientRect() {
    return {
      left: 0,
      top: 0,
      right: this.clientWidth,
      bottom: this.clientHeight,
      width: this.clientWidth,
      height: this.clientHeight,
      x: 0,
      y: 0,
    };
  }
  setPointerCapture() {}
  releasePointerCapture() {}
  hasPointerCapture() {
    return false;
  }
  focus() {}
}

let renderer: THREE.WebGLRenderer | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let controls: OrbitControls | null = null;
let built: BuiltScene | null = null;
let scene: THREE.Scene | null = null;
const proxy = new ElementProxy();
let visible = true;
let playing = true;
let elapsed = 0;
let last = 0;
let lastTick = 0;
let homeCamera: StepCamera | null = null;
let autoRotate = false;
const frameCallbacks: ((delta: number, elapsed: number) => void)[] = [];

const raf = (cb: (t: number) => void) =>
  scope.requestAnimationFrame
    ? scope.requestAnimationFrame(cb)
    : (setTimeout(() => cb(performance.now()), 16) as unknown as number);

scope.onmessage = (event) => {
  const message = event.data;
  try {
    switch (message.type) {
      case "init":
        return init(message);
      case "resize":
        return resize(message.width, message.height);
      case "event":
        return dispatch(message.event);
      case "visibility":
        visible = message.visible;
        return;
      case "playing":
        playing = message.playing;
        return;
      case "step":
        return goToStep(message.index);
      case "reset":
        return moveCamera(homeCamera);
      case "theme":
        return; // The frame re-renders the block on theme change.
    }
  } catch (error) {
    fail(error);
  }
};

function init(message: WorkerInit) {
  playing = !message.reducedMotion;
  renderer = new THREE.WebGLRenderer({ canvas: message.canvas, antialias: true, powerPreference: "low-power" });
  renderer.setPixelRatio(Math.min(message.pixelRatio, MAX_PIXEL_RATIO));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  camera = new THREE.PerspectiveCamera(50, message.width / Math.max(1, message.height), 0.1, 5000);
  resize(message.width, message.height);

  if (message.kind === "scene-3d") {
    const parsed = sceneSchema.safeParse(message.payload);
    if (!parsed.success) throw new Error(`Invalid scene: ${parsed.error.issues[0]?.message ?? "unknown"}`);
    const data = parsed.data;
    built = buildScene(data, message.theme);
    scene = built.scene;
    camera.fov = data.camera.fov ?? 50;
    camera.updateProjectionMatrix();
    homeCamera = built.initialCamera;
    if ((data.camera.controls ?? "orbit") === "orbit") createControls(data.camera.autoRotate ?? false);
    moveCamera(homeCamera);
    if (data.steps?.length) goToStep(0);
    send({
      type: "started",
      animated: Boolean(
        data.camera.autoRotate || data.objects.some((o) => o.animation) || data.links?.some((l) => l.flow),
      ),
    });
  } else {
    const parsed = scriptSchema.safeParse(message.payload);
    if (!parsed.success) throw new Error("Invalid script block.");
    scene = new THREE.Scene();
    scene.background = new THREE.Color(message.theme === "dark" ? "#0b1220" : "#f4f6f9");
    camera.position.set(4, 3, 6);
    createControls(false);
    homeCamera = { position: camera.position.clone(), target: new THREE.Vector3() };
    runAuthorCode(parsed.data.code, message.theme, message.reducedMotion);
    send({ type: "started", animated: frameCallbacks.length > 0 });
  }

  last = performance.now();
  raf(loop);
}

function createControls(rotate: boolean) {
  controls = new OrbitControls(camera!, proxy as unknown as HTMLElement);
  controls.enableDamping = true;
  controls.autoRotate = rotate;
  autoRotate = rotate;
  controls.autoRotateSpeed = 1.2;
}

function resize(width: number, height: number) {
  if (!renderer || !camera) return;
  proxy.clientWidth = width;
  proxy.clientHeight = height;
  renderer.setSize(width, height, false);
  camera.aspect = width / Math.max(1, height);
  camera.updateProjectionMatrix();
}

function moveCamera(target: StepCamera | null) {
  if (!camera || !target) return;
  camera.position.copy(target.position);
  if (controls) controls.target.copy(target.target);
  else camera.lookAt(target.target);
  controls?.update();
}

function goToStep(index: number) {
  if (!built) return;
  const stepCamera = built.applyStep(index);
  if (stepCamera) moveCamera(stepCamera);
}

const raycaster = new THREE.Raycaster();
let downAt: { x: number; y: number } | null = null;

function dispatch(event: ForwardedEvent) {
  const synthetic = { ...event, preventDefault() {}, stopPropagation() {} };
  if (event.type === "pointerdown") downAt = { x: event.clientX ?? 0, y: event.clientY ?? 0 };
  if (event.type === "click") return pick(event);
  proxy.dispatchEvent(synthetic as never);
}

function pick(event: ForwardedEvent) {
  if (!built || !camera || built.pickables.size === 0) return;
  if (downAt && Math.hypot((event.clientX ?? 0) - downAt.x, (event.clientY ?? 0) - downAt.y) > 6) return; // a drag, not a click
  const x = ((event.clientX ?? 0) / proxy.clientWidth) * 2 - 1;
  const y = -((event.clientY ?? 0) / proxy.clientHeight) * 2 + 1;
  raycaster.setFromCamera(new THREE.Vector2(x, y), camera);
  const hit = raycaster.intersectObjects(
    [...built.pickables.keys()].filter((o) => o.visible),
    false,
  )[0];
  send({ type: "select", id: hit ? (built.pickables.get(hit.object) ?? null) : null });
}

function loop(now: number) {
  const delta = Math.min(0.1, (now - last) / 1000);
  last = now;
  if (visible && renderer && scene && camera) {
    if (playing) elapsed += delta;
    if (controls) {
      controls.autoRotate = autoRotate && playing;
      controls.update(delta);
    }
    built?.update(elapsed, delta, playing);
    if (playing) {
      for (let i = 0; i < frameCallbacks.length; i++) {
        try {
          frameCallbacks[i](delta, elapsed);
        } catch (error) {
          frameCallbacks.splice(i, 1);
          fail(error);
          return;
        }
      }
    }
    renderer.render(scene, camera);
  }
  // The heartbeat the frame's watchdog listens for (every ~250 ms).
  if (now - lastTick > 250) {
    lastTick = now;
    send({ type: "tick" });
  }
  raf(loop);
}

/**
 * Runs script-3d code. Network and worker APIs are removed from this scope first; that is
 * defence in depth only, the CSP inherited from the frame is what actually blocks them.
 */
function runAuthorCode(code: string, theme: Theme, reducedMotion: boolean) {
  lockDown();
  const api = Object.freeze({
    THREE,
    scene: scene!,
    camera: camera!,
    controls: controls!,
    renderer: renderer!,
    theme,
    reducedMotion,
    onFrame: (fn: (delta: number, elapsed: number) => void) => {
      if (typeof fn === "function" && frameCallbacks.length < 32) frameCallbacks.push(fn);
    },
    label: (text: string, options?: { color?: string; scale?: number }) =>
      makeLabel(String(text).slice(0, 60), theme, options),
  });
  // Line numbers: "use strict" is line 1 of the body, and Function adds two header lines.
  const run = new Function(
    "api",
    `"use strict";\nconst { THREE, scene, camera, controls, renderer, onFrame, label, theme, reducedMotion } = api;\n${code}`,
  );
  run(api);
}

const LINE_OFFSET = 4;

function lockDown() {
  const g = self as unknown as Record<string, unknown>;
  for (const name of [
    "fetch",
    "XMLHttpRequest",
    "WebSocket",
    "EventSource",
    "importScripts",
    "Worker",
    "SharedWorker",
    "indexedDB",
    "caches",
    "BroadcastChannel",
    "WebTransport",
    "Request",
  ]) {
    try {
      let target: object | null = self;
      while (target) {
        if (Object.prototype.hasOwnProperty.call(target, name)) delete (target as Record<string, unknown>)[name];
        target = Object.getPrototypeOf(target);
      }
      Object.defineProperty(g, name, { value: undefined, configurable: false, writable: false });
    } catch {
      // Not every name exists in every browser.
    }
  }
}

function fail(error: unknown) {
  const err = error instanceof Error ? error : new Error(String(error));
  const match = /<anonymous>:(\d+):\d+/.exec(err.stack ?? "");
  const line = match ? Number(match[1]) - LINE_OFFSET : undefined;
  send({
    type: "error",
    message: `${err.name}: ${err.message}`.slice(0, 300),
    line: line && line > 0 ? line : undefined,
  });
}
