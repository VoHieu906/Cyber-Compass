import { z } from "zod";
import "@/lib/zod-config";

/*
 * zod mirror of the course map contracts (F3, D57): backend/app/Modules/Content/CourseMap/schemas
 * and the reference checks in MapSchemas.php. contract.test.ts holds both sides to the shared
 * fixtures. The renderer parses with these before drawing, so a malformed map shows nothing
 * rather than breaking the course page, and the admin panels use them for their live previews.
 */

export const SHAPES = [
  "layers",
  "onion",
  "stack",
  "queue",
  "pipeline",
  "cycle",
  "hub",
  "tree",
  "mesh",
  "matrix",
] as const;
export type ShapeId = (typeof SHAPES)[number];

export const EDGE_TYPES = ["requires", "builds-on", "feeds-into", "contrasts-with", "parallel-to"] as const;
export type EdgeType = (typeof EDGE_TYPES)[number];

/** Sub-node count per shape, as in shapes/<shape>.json. */
export const SHAPE_LIMITS: Record<ShapeId, { min: number; max: number }> = {
  layers: { min: 2, max: 8 },
  onion: { min: 2, max: 6 },
  stack: { min: 2, max: 8 },
  queue: { min: 2, max: 8 },
  pipeline: { min: 2, max: 8 },
  cycle: { min: 3, max: 8 },
  hub: { min: 3, max: 12 },
  tree: { min: 2, max: 12 },
  mesh: { min: 2, max: 12 },
  matrix: { min: 2, max: 12 },
};

/** Node fields beyond id, label, note, section and lesson, per shape. */
const SHAPE_FIELDS: Partial<Record<ShapeId, ("parent" | "x" | "y" | "cell")[]>> = {
  tree: ["parent"],
  mesh: ["x", "y"],
  matrix: ["cell"],
};

const text = (max: number) => z.string().min(1).max(max);
const id = z.string().regex(/^[a-z0-9][a-z0-9-]{0,39}$/);
const slug = z
  .string()
  .max(120)
  .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/);
const version = z.number().int().min(1).max(1);

const subNode = z.strictObject({
  id,
  label: text(40),
  note: text(240).optional(),
  section: z.uuid().optional(),
  lesson: slug.optional(),
  parent: id.optional(),
  x: z.number().min(0).max(100).optional(),
  y: z.number().min(0).max(100).optional(),
  cell: z.tuple([z.number().int().min(0), z.number().int().min(0)]).optional(),
});
export type SubNode = z.infer<typeof subNode>;

export const architectureSchema = z
  .strictObject({
    version,
    problem: text(140),
    shape: z.enum(SHAPES).nullable(),
    rows: z.array(text(30)).optional(),
    cols: z.array(text(30)).optional(),
    nodes: z.array(subNode).max(12),
    edges: z.array(z.strictObject({ from: id, to: id, label: text(24).optional() })).max(20),
  })
  .superRefine((a, ctx) => {
    const issue = (path: (string | number)[], message: string) => ctx.addIssue({ code: "custom", path, message });
    if (a.shape === null) {
      if (a.nodes.length) issue(["shape"], "Choose a shape for these nodes, or remove them.");
    } else {
      const { min, max } = SHAPE_LIMITS[a.shape];
      if (a.nodes.length < min) issue(["nodes"], `Add at least ${min} items.`);
      if (a.nodes.length > max) issue(["nodes"], `Use at most ${max} items.`);
      const allowed = SHAPE_FIELDS[a.shape] ?? [];
      a.nodes.forEach((n, i) => {
        for (const field of ["parent", "x", "y", "cell"] as const) {
          if (n[field] !== undefined && !allowed.includes(field)) issue(["nodes", i], `Unknown field: ${field}.`);
        }
        if (a.shape === "mesh")
          for (const f of ["x", "y"] as const)
            if (n[f] === undefined) issue(["nodes", i, f], "This field is required.");
        if (a.shape === "matrix" && !n.cell) issue(["nodes", i, "cell"], "This field is required.");
      });
    }
    if (a.shape === "matrix") {
      if (!a.rows || a.rows.length < 1 || a.rows.length > 4) issue(["rows"], "Use 1 to 4 rows.");
      if (!a.cols || a.cols.length < 2 || a.cols.length > 3) issue(["cols"], "Use 2 or 3 columns.");
    } else {
      if (a.rows) issue(["rows"], "Only a matrix has rows and columns.");
      if (a.cols) issue(["cols"], "Only a matrix has rows and columns.");
    }

    const ids = new Map<string, number>();
    a.nodes.forEach((n, i) => {
      if (ids.has(n.id)) issue(["nodes", i, "id"], `Duplicate id "${n.id}".`);
      ids.set(n.id, i);
    });
    const seen = new Set<string>();
    a.edges.forEach((e, i) => {
      for (const end of ["from", "to"] as const)
        if (!ids.has(e[end])) issue(["edges", i, end], `Unknown id "${e[end]}".`);
      if (e.from === e.to) issue(["edges", i, "to"], "An edge needs two different nodes.");
      else if (seen.has(`${e.from}>${e.to}`)) issue(["edges", i], "This edge is listed twice.");
      seen.add(`${e.from}>${e.to}`);
    });

    if (a.shape === "tree") {
      let roots = 0;
      a.nodes.forEach((n, i) => {
        if (n.parent === undefined) return void roots++;
        if (!ids.has(n.parent)) return issue(["nodes", i, "parent"], `Unknown id "${n.parent}".`);
        let p: string | undefined = n.parent;
        let steps = 0;
        while (p !== undefined && steps <= a.nodes.length) {
          p = a.nodes[ids.get(p) ?? -1]?.parent;
          steps++;
        }
        if (steps > a.nodes.length) issue(["nodes", i, "parent"], "These parents form a loop.");
      });
      if (roots !== 1) issue(["nodes"], "A tree needs exactly one node without a parent.");
    }
    if (a.shape === "matrix" && a.rows && a.cols) {
      const taken = new Set<string>();
      a.nodes.forEach((n, i) => {
        if (!n.cell) return;
        const [r, c] = n.cell;
        if (r >= a.rows!.length || c >= a.cols!.length) issue(["nodes", i, "cell"], "This cell is outside the grid.");
        else if (taken.has(`${r}:${c}`)) issue(["nodes", i, "cell"], "Another node already sits in this cell.");
        taken.add(`${r}:${c}`);
      });
    }
  });
export type Architecture = z.infer<typeof architectureSchema>;

const lane = z.string().regex(/^[a-z0-9][a-z0-9-]{0,39}$/);

export const courseGraphSchema = z
  .strictObject({
    version,
    problem: text(200),
    layout: z.enum(["auto", "manual"]),
    lanes: z
      .array(z.strictObject({ id: lane, label: text(30) }))
      .max(6)
      .optional(),
    placement: z
      .record(
        slug,
        z.strictObject({
          col: z.number().int().min(0).max(7),
          row: z.number().int().min(0).max(9),
          lane: lane.optional(),
        }),
      )
      .optional(),
    edges: z
      .array(z.strictObject({ from: slug, to: slug, type: z.enum(EDGE_TYPES), label: text(24).optional() }))
      .max(80),
  })
  .superRefine((g, ctx) => {
    const issue = (path: (string | number)[], message: string) => ctx.addIssue({ code: "custom", path, message });
    if (Object.keys(g.placement ?? {}).length > 40) issue(["placement"], "Use at most 40 items.");
    const lanes = new Set<string>();
    g.lanes?.forEach((l, i) => {
      if (lanes.has(l.id)) issue(["lanes", i, "id"], `Duplicate id "${l.id}".`);
      lanes.add(l.id);
    });
    for (const [s, place] of Object.entries(g.placement ?? {})) {
      if (place.lane && !lanes.has(place.lane)) issue(["placement", s, "lane"], `Unknown id "${place.lane}".`);
    }
    const seen = new Set<string>();
    g.edges.forEach((e, i) => {
      if (e.from === e.to) issue(["edges", i, "to"], "An edge needs two different lessons.");
      const key = `${e.from}>${e.to}>${e.type}`;
      if (seen.has(key)) issue(["edges", i], "This edge is listed twice.");
      seen.add(key);
    });
  });
export type CourseGraph = z.infer<typeof courseGraphSchema>;
