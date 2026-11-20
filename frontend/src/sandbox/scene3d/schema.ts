import { z } from "zod";

/*
 * The 3D block contracts (docs/SANDBOXED_3D.md). The canonical rules are the JSON Schemas in
 * backend/app/Modules/Content/Blocks/schemas/{scene-3d,script-3d}.json (ARCHITECTURE D18);
 * these zod schemas mirror them and both are checked against the same fixtures.
 * Used by the editor (live validation), the sandbox frame (validated again before drawing)
 * and the learner renderer.
 */

export const LIMITS = {
  objects: 150,
  links: 150,
  lights: 8,
  steps: 20,
  flowPackets: 5,
  coordinate: 1000,
  scriptBytes: 20_000,
} as const;

const coord = z.number().min(-LIMITS.coordinate).max(LIMITS.coordinate);
const vec3 = z.tuple([coord, coord, coord]);
const positive3 = z.tuple([
  z.number().min(0.01).max(500),
  z.number().min(0.01).max(500),
  z.number().min(0.01).max(500),
]);
const color = z.string().regex(/^#[0-9a-fA-F]{6}$/);
const id = z.string().regex(/^[a-z0-9][a-z0-9-]{0,39}$/);

export const ASPECTS = ["16:9", "4:3", "1:1", "21:9"] as const;
export const SHAPES = [
  "box",
  "sphere",
  "cylinder",
  "cone",
  "torus",
  "plane",
  "capsule",
  "ring",
  "label",
  "group",
] as const;

const common = {
  title: z.string().trim().min(1).max(120),
  description: z.string().trim().min(20).max(1200),
  caption: z.string().max(300).nullish(),
  aspect: z.enum(ASPECTS).default("16:9"),
};

const objectSchema = z
  .object({
    id,
    shape: z.enum(SHAPES),
    size: positive3.optional(),
    position: vec3.optional(),
    rotation: vec3.optional(),
    color: color.optional(),
    opacity: z.number().min(0.05).max(1).optional(),
    wireframe: z.boolean().optional(),
    emissive: z.boolean().optional(),
    text: z.string().min(1).max(60).optional(),
    info: z
      .object({ title: z.string().min(1).max(80), text: z.string().min(1).max(600) })
      .strict()
      .optional(),
    parent: id.optional(),
    animation: z
      .object({
        type: z.enum(["spin", "bob", "pulse", "orbit"]),
        axis: z.enum(["x", "y", "z"]).optional(),
        speed: z.number().min(0.1).max(10).optional(),
        amount: z.number().min(0).max(20).optional(),
      })
      .strict()
      .optional(),
  })
  .strict();

const linkSchema = z
  .object({
    from: id,
    to: id,
    color: color.optional(),
    dashed: z.boolean().optional(),
    label: z.string().min(1).max(40).optional(),
    flow: z
      .object({
        speed: z.number().min(0.1).max(5).optional(),
        color: color.optional(),
        count: z.number().int().min(1).max(LIMITS.flowPackets).optional(),
      })
      .strict()
      .optional(),
  })
  .strict();

const cameraSchema = z
  .object({
    position: vec3,
    target: vec3.optional(),
    fov: z.number().min(20).max(100).optional(),
    controls: z.enum(["orbit", "none"]).optional(),
    autoRotate: z.boolean().optional(),
  })
  .strict();

const stepSchema = z
  .object({
    title: z.string().min(1).max(80),
    text: z.string().min(1).max(600),
    show: z.array(id).max(LIMITS.objects).optional(),
    hide: z.array(id).max(LIMITS.objects).optional(),
    highlight: z.array(id).max(LIMITS.objects).optional(),
    camera: z.object({ position: vec3, target: vec3.optional() }).strict().optional(),
  })
  .strict();

export const sceneSchema = z
  .object({
    ...common,
    background: color.nullish(),
    camera: cameraSchema,
    lights: z
      .array(
        z
          .object({
            type: z.enum(["ambient", "directional", "point", "hemisphere"]),
            color: color.optional(),
            intensity: z.number().min(0).max(10),
            position: vec3.optional(),
          })
          .strict(),
      )
      .max(LIMITS.lights)
      .optional(),
    objects: z.array(objectSchema).min(1).max(LIMITS.objects),
    links: z.array(linkSchema).max(LIMITS.links).optional(),
    steps: z.array(stepSchema).max(LIMITS.steps).optional(),
  })
  .strict()
  .superRefine((scene, ctx) => {
    // Cross-references JSON Schema can't express; the backend checks the same (Scene3dBlock).
    const ids = new Set<string>();
    scene.objects.forEach((o, i) => {
      if (ids.has(o.id))
        ctx.addIssue({ code: "custom", path: ["objects", i, "id"], message: `Duplicate id "${o.id}".` });
      ids.add(o.id);
    });
    const parents = new Map(scene.objects.map((o) => [o.id, o.parent]));
    scene.objects.forEach((o, i) => {
      if (o.parent === undefined) return;
      if (!ids.has(o.parent))
        ctx.addIssue({ code: "custom", path: ["objects", i, "parent"], message: `Unknown object "${o.parent}".` });
      // Walk up the chain: a cycle would loop forever when building the scene.
      const seen = new Set([o.id]);
      for (let p: string | undefined = o.parent; p !== undefined; p = parents.get(p)) {
        if (seen.has(p)) {
          ctx.addIssue({ code: "custom", path: ["objects", i, "parent"], message: "Parents form a loop." });
          break;
        }
        seen.add(p);
      }
    });
    scene.links?.forEach((l, i) => {
      for (const end of ["from", "to"] as const) {
        if (!ids.has(l[end]))
          ctx.addIssue({ code: "custom", path: ["links", i, end], message: `Unknown object "${l[end]}".` });
      }
    });
    scene.steps?.forEach((s, i) => {
      for (const list of ["show", "hide", "highlight"] as const) {
        s[list]?.forEach((ref, j) => {
          if (!ids.has(ref))
            ctx.addIssue({ code: "custom", path: ["steps", i, list, j], message: `Unknown object "${ref}".` });
        });
      }
    });
  });

export const scriptSchema = z
  .object({
    ...common,
    code: z.string().min(1).max(LIMITS.scriptBytes),
  })
  .strict();

export type Scene3d = z.infer<typeof sceneSchema>;
export type SceneObject = Scene3d["objects"][number];
export type SceneLink = NonNullable<Scene3d["links"]>[number];
export type SceneStep = NonNullable<Scene3d["steps"]>[number];
export type Script3d = z.infer<typeof scriptSchema>;
export type Aspect = (typeof ASPECTS)[number];

export const aspectRatio = (aspect: Aspect | undefined): number => {
  const [w, h] = (aspect ?? "16:9").split(":").map(Number);
  return w / h;
};

/** zod issues -> "objects.3.parent: Unknown object" lines, the same paths the server uses. */
export function describeIssues(error: z.ZodError, limit = 8): { path: string; message: string }[] {
  return error.issues.slice(0, limit).map((issue) => ({ path: issue.path.join("."), message: issue.message }));
}
