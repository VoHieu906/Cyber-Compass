import * as THREE from "three";
import type { Theme } from "./protocol";
import type { Scene3d, SceneObject } from "./schema";
import { stepState } from "./steps";

/*
 * Turns a validated scene-3d payload into Three.js objects. Runs in the sandbox worker;
 * nothing here executes author code: the payload is data.
 */

const DEG = Math.PI / 180;

const palette = {
  light: {
    background: "#f4f6f9",
    object: "#3b82f6",
    link: "#64748b",
    flow: "#f59e0b",
    label: "#0f172a",
    labelBg: "rgba(255,255,255,0.88)",
  },
  dark: {
    background: "#0b1220",
    object: "#38bdf8",
    link: "#94a3b8",
    flow: "#fbbf24",
    label: "#e2e8f0",
    labelBg: "rgba(15,23,42,0.85)",
  },
};

export type BuiltScene = {
  scene: THREE.Scene;
  /** Meshes that answer clicks, mapped to their object id. */
  pickables: Map<THREE.Object3D, string>;
  /** Advances animations; `playing` false freezes them on the current frame. */
  update: (elapsed: number, delta: number, playing: boolean) => void;
  applyStep: (index: number) => StepCamera | null;
  initialCamera: StepCamera;
  dispose: () => void;
};
export type StepCamera = { position: THREE.Vector3; target: THREE.Vector3 };

const unitGeometry: Record<Exclude<SceneObject["shape"], "label" | "group">, () => THREE.BufferGeometry> = {
  box: () => new THREE.BoxGeometry(1, 1, 1),
  sphere: () => new THREE.SphereGeometry(0.5, 32, 16),
  cylinder: () => new THREE.CylinderGeometry(0.5, 0.5, 1, 32),
  cone: () => new THREE.ConeGeometry(0.5, 1, 32),
  torus: () => new THREE.TorusGeometry(0.4, 0.1, 16, 48),
  plane: () => new THREE.PlaneGeometry(1, 1),
  capsule: () => new THREE.CapsuleGeometry(0.3, 0.4, 8, 16),
  ring: () => new THREE.RingGeometry(0.3, 0.5, 48),
};

/** A text label drawn on a 2D canvas and shown as a sprite that always faces the camera. */
export function makeLabel(text: string, theme: Theme, options: { color?: string; scale?: number } = {}): THREE.Sprite {
  const colors = palette[theme];
  const fontSize = 48;
  const pad = 18;
  const measure = new OffscreenCanvas(1, 1).getContext("2d")!;
  measure.font = `600 ${fontSize}px system-ui, sans-serif`;
  const width = Math.ceil(measure.measureText(text).width) + pad * 2;
  const height = fontSize + pad * 2;
  const canvas = new OffscreenCanvas(width, height);
  const ctx = canvas.getContext("2d")!;
  ctx.fillStyle = colors.labelBg;
  ctx.beginPath();
  ctx.roundRect(0, 0, width, height, 14);
  ctx.fill();
  ctx.font = `600 ${fontSize}px system-ui, sans-serif`;
  ctx.fillStyle = options.color ?? colors.label;
  ctx.textBaseline = "middle";
  ctx.fillText(text, pad, height / 2 + 2);

  const texture = new THREE.CanvasTexture(canvas as unknown as HTMLCanvasElement);
  texture.colorSpace = THREE.SRGBColorSpace;
  const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: texture, depthTest: false, transparent: true }));
  const scale = (options.scale ?? 1) * 0.0045;
  sprite.scale.set(width * scale, height * scale, 1);
  sprite.renderOrder = 10;
  return sprite;
}

export function buildScene(data: Scene3d, theme: Theme): BuiltScene {
  const colors = palette[theme];
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(data.background ?? colors.background);

  const lights = data.lights?.length
    ? data.lights
    : [
        { type: "hemisphere" as const, intensity: 1.2 },
        { type: "directional" as const, intensity: 1.6, position: [5, 10, 7] as [number, number, number] },
      ];
  for (const l of lights) {
    const c = new THREE.Color(l.color ?? "#ffffff");
    const light =
      l.type === "ambient"
        ? new THREE.AmbientLight(c, l.intensity)
        : l.type === "hemisphere"
          ? new THREE.HemisphereLight(c, new THREE.Color("#445566"), l.intensity)
          : l.type === "point"
            ? new THREE.PointLight(c, l.intensity * 10, 0, 1.5)
            : new THREE.DirectionalLight(c, l.intensity);
    if (l.position && "position" in light) light.position.set(...l.position);
    scene.add(light);
  }

  // Objects: create all, then attach to parents (validated: parents exist, no loops).
  const nodes = new Map<string, THREE.Object3D>();
  const materials = new Map<string, THREE.MeshStandardMaterial>();
  const pickables = new Map<THREE.Object3D, string>();
  const base = new Map<string, { position: THREE.Vector3; rotation: THREE.Euler; scale: THREE.Vector3 }>();

  for (const o of data.objects) {
    let node: THREE.Object3D;
    if (o.shape === "group") {
      node = new THREE.Group();
    } else if (o.shape === "label") {
      node = makeLabel(o.text ?? o.id, theme, { color: o.color });
    } else {
      const material = new THREE.MeshStandardMaterial({
        color: new THREE.Color(o.color ?? colors.object),
        transparent: (o.opacity ?? 1) < 1,
        opacity: o.opacity ?? 1,
        wireframe: o.wireframe ?? false,
        roughness: 0.55,
        metalness: 0.1,
        side: o.shape === "plane" || o.shape === "ring" ? THREE.DoubleSide : THREE.FrontSide,
      });
      if (o.emissive) material.emissive = new THREE.Color(o.color ?? colors.object).multiplyScalar(0.45);
      materials.set(o.id, material);
      node = new THREE.Mesh(unitGeometry[o.shape](), material);
      if (o.info) pickables.set(node, o.id);
      if (o.text) {
        const tag = makeLabel(o.text, theme);
        // Above the object, in the object's own units so it follows its scale.
        const size = o.size ?? [1, 1, 1];
        tag.position.set(0, 0.5 + 0.35 / size[1], 0);
        tag.scale.divide(new THREE.Vector3(size[0], size[1], 1));
        node.add(tag);
      }
    }
    node.name = o.id;
    if (o.size && o.shape !== "label") node.scale.set(...o.size);
    if (o.position) node.position.set(...o.position);
    if (o.rotation) node.rotation.set(o.rotation[0] * DEG, o.rotation[1] * DEG, o.rotation[2] * DEG);
    base.set(o.id, { position: node.position.clone(), rotation: node.rotation.clone(), scale: node.scale.clone() });
    nodes.set(o.id, node);
  }
  for (const o of data.objects) {
    const parent = o.parent ? nodes.get(o.parent) : undefined;
    (parent ?? scene).add(nodes.get(o.id)!);
  }

  // Links: straight lines between object centres (world space), refreshed each frame so they
  // follow animated objects. Flowing packets travel from -> to.
  const linkObjects = (data.links ?? []).map((link) => {
    const geometry = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(), new THREE.Vector3()]);
    const material = link.dashed
      ? new THREE.LineDashedMaterial({ color: link.color ?? colors.link, dashSize: 0.25, gapSize: 0.15 })
      : new THREE.LineBasicMaterial({ color: link.color ?? colors.link });
    const line = new THREE.Line(geometry, material);
    scene.add(line);
    const packets = Array.from({ length: link.flow ? (link.flow.count ?? 1) : 0 }, () => {
      const packet = new THREE.Mesh(
        new THREE.SphereGeometry(0.09, 16, 8),
        new THREE.MeshBasicMaterial({ color: link.flow?.color ?? colors.flow }),
      );
      scene.add(packet);
      return packet;
    });
    const label = link.label ? makeLabel(link.label, theme, { scale: 0.8 }) : null;
    if (label) scene.add(label);
    return { link, line, packets, label };
  });

  const a = new THREE.Vector3();
  const b = new THREE.Vector3();
  let visible = new Set(data.objects.map((o) => o.id));

  function refreshLinks(elapsed: number) {
    for (const { link, line, packets, label } of linkObjects) {
      const from = nodes.get(link.from)!;
      const to = nodes.get(link.to)!;
      const shown = from.visible && to.visible && isShown(link.from) && isShown(link.to);
      line.visible = shown;
      from.getWorldPosition(a);
      to.getWorldPosition(b);
      line.geometry.setFromPoints([a, b]);
      if (line.material instanceof THREE.LineDashedMaterial) line.computeLineDistances();
      if (label) {
        label.visible = shown;
        label.position
          .copy(a)
          .lerp(b, 0.5)
          .add(new THREE.Vector3(0, 0.25, 0));
      }
      const speed = link.flow?.speed ?? 1;
      packets.forEach((p, i) => {
        p.visible = shown;
        const t = (elapsed * speed * 0.35 + i / packets.length) % 1;
        p.position.copy(a).lerp(b, t);
      });
    }
  }

  function isShown(id: string): boolean {
    for (let node: THREE.Object3D | null = nodes.get(id) ?? null; node && node !== scene; node = node.parent) {
      if (!node.visible) return false;
    }
    return true;
  }

  function update(elapsed: number, _delta: number, playing: boolean) {
    if (playing) {
      for (const o of data.objects) {
        const anim = o.animation;
        if (!anim) continue;
        const node = nodes.get(o.id)!;
        const start = base.get(o.id)!;
        const speed = anim.speed ?? 1;
        const amount = anim.amount ?? 1;
        const axis = anim.axis ?? "y";
        if (anim.type === "spin") node.rotation[axis] = start.rotation[axis] + elapsed * speed;
        if (anim.type === "bob")
          node.position[axis] = start.position[axis] + Math.sin(elapsed * speed * 2) * 0.25 * amount;
        if (anim.type === "pulse")
          node.scale.copy(start.scale).multiplyScalar(1 + Math.sin(elapsed * speed * 3) * 0.08 * amount);
        if (anim.type === "orbit") {
          const angle = elapsed * speed;
          const r = amount || 1;
          const [u, v] =
            axis === "y" ? (["x", "z"] as const) : axis === "x" ? (["y", "z"] as const) : (["x", "y"] as const);
          node.position.copy(start.position);
          node.position[u] += Math.cos(angle) * r;
          node.position[v] += Math.sin(angle) * r;
        }
      }
    }
    refreshLinks(playing ? elapsed : frozenAt);
    if (playing) frozenAt = elapsed;
  }
  let frozenAt = 0;

  function applyStep(index: number): StepCamera | null {
    const state = stepState(data, index);
    visible = state.visible;
    for (const [id, node] of nodes) node.visible = visible.has(id);
    const dim = state.highlighted.size > 0;
    for (const [id, material] of materials) {
      const source = data.objects.find((o) => o.id === id)!;
      const lit = state.highlighted.has(id);
      material.opacity = dim && !lit ? 0.18 : (source.opacity ?? 1);
      material.transparent = material.opacity < 1;
      material.emissive = lit
        ? new THREE.Color(colors.flow).multiplyScalar(0.55)
        : source.emissive
          ? new THREE.Color(source.color ?? colors.object).multiplyScalar(0.45)
          : new THREE.Color(0x000000);
      material.needsUpdate = true;
    }
    refreshLinks(frozenAt);
    return state.camera
      ? { position: new THREE.Vector3(...state.camera.position), target: new THREE.Vector3(...state.camera.target) }
      : null;
  }

  refreshLinks(0);

  return {
    scene,
    pickables,
    update,
    applyStep,
    initialCamera: {
      position: new THREE.Vector3(...data.camera.position),
      target: new THREE.Vector3(...(data.camera.target ?? [0, 0, 0])),
    },
    dispose: () => {
      scene.traverse((node) => {
        const mesh = node as THREE.Mesh;
        mesh.geometry?.dispose();
        const material = mesh.material as THREE.Material | THREE.Material[] | undefined;
        (Array.isArray(material) ? material : material ? [material] : []).forEach((m) => {
          (m as THREE.SpriteMaterial).map?.dispose();
          m.dispose();
        });
      });
    },
  };
}
