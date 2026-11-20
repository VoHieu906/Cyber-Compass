import type { Scene3d } from "./schema";

export type StepState = {
  /** Objects drawn in this step. */
  visible: Set<string>;
  /** Objects called out in this step; when non-empty, everything else is dimmed. */
  highlighted: Set<string>;
  camera: { position: [number, number, number]; target: [number, number, number] } | null;
};

/**
 * What a guided step shows. Steps build on each other: an object hidden in step 2 stays
 * hidden in step 3 unless step 3 shows it again, so authors only describe what changes.
 * Index -1 (or a scene without steps) is the free view: everything visible, nothing dimmed.
 */
export function stepState(scene: Scene3d, index: number): StepState {
  const visible = new Set(scene.objects.map((o) => o.id));
  const steps = scene.steps ?? [];
  if (index < 0 || steps.length === 0) return { visible, highlighted: new Set(), camera: null };

  let camera: StepState["camera"] = null;
  const last = Math.min(index, steps.length - 1);
  for (let i = 0; i <= last; i++) {
    const step = steps[i];
    step.hide?.forEach((id) => visible.delete(id));
    step.show?.forEach((id) => visible.add(id));
    if (step.camera) camera = { position: step.camera.position, target: step.camera.target ?? [0, 0, 0] };
  }
  return { visible, highlighted: new Set(steps[last].highlight ?? []), camera };
}

/** The id chain from an object up to the root, used to hide children with their parent. */
export function ancestors(scene: Scene3d, id: string): string[] {
  const parent = new Map(scene.objects.map((o) => [o.id, o.parent]));
  const chain: string[] = [];
  for (let p = parent.get(id); p !== undefined && !chain.includes(p); p = parent.get(p)) chain.push(p);
  return chain;
}

/** Whether a scene has anything that moves, so the frame knows to offer play/pause. */
export function isAnimated(scene: Scene3d): boolean {
  return Boolean(scene.camera.autoRotate || scene.objects.some((o) => o.animation) || scene.links?.some((l) => l.flow));
}
