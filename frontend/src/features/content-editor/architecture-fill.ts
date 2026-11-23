import type { Architecture, ShapeId, SubNode } from "@/lib/graph/shapes/schemas";
import { SHAPE_LIMITS } from "@/lib/graph/shapes/schemas";
import type { EditorSection } from "./types";

/*
 * "Fill from block" in the Architecture panel (F3, D59): starts an architecture from a block of
 * the same lesson, once. The architecture keeps its own data afterwards; every part links back
 * to the block. Only blocks whose knowledge already has a shape are offered.
 */

export const FILLABLE: Record<string, ShapeId> = {
  "layer-stack": "layers",
  flow: "pipeline",
  tree: "tree",
  topology: "mesh",
  comparison: "matrix",
};

const clip = (text: string, max: number) => (text.length > max ? `${text.slice(0, max - 1).trimEnd()}…` : text);
const idFrom = (text: string, i: number, taken: Set<string>) => {
  const base =
    text
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-|-$/g, "")
      .slice(0, 30) || `part-${i + 1}`;
  let id = base;
  for (let n = 2; taken.has(id); n++) id = `${base}-${n}`;
  taken.add(id);
  return id;
};

type Payload = Record<string, unknown>;
type Item = {
  id?: string;
  label?: string;
  name?: string;
  detail?: string;
  description?: string;
  parent?: string;
  x?: number;
  y?: number;
};

/** The architecture a block suggests, or null for a block that is not fillable or not saved yet. */
export function fillFromBlock(section: EditorSection, problem: string): Architecture | null {
  const shape = FILLABLE[section.type];
  if (!shape || !section.id) return null;
  const max = SHAPE_LIMITS[shape].max;
  const taken = new Set<string>();
  const p = section.payload as Payload;
  const base = { version: 1 as const, problem, shape, edges: [] as Architecture["edges"] };
  const part = (label: string, i: number, note?: string, extra: Partial<SubNode> = {}): SubNode => ({
    id: idFrom(label, i, taken),
    label: clip(label, 40),
    ...(note ? { note: clip(note, 240) } : {}),
    section: section.id,
    ...extra,
  });

  switch (section.type) {
    case "layer-stack":
      return {
        ...base,
        nodes: ((p.layers as Item[]) ?? []).slice(0, max).map((l, i) => part(l.name ?? "", i, l.description)),
      };
    case "flow":
      return {
        ...base,
        nodes: ((p.nodes as Item[]) ?? []).slice(0, max).map((n, i) => part(n.label ?? "", i, n.detail)),
      };
    case "tree": {
      const items = ((p.nodes as Item[]) ?? []).slice(0, max);
      const ids = new Map(items.map((n, i) => [n.id!, idFrom(n.label ?? "", i, taken)]));
      return {
        ...base,
        nodes: items.map((n) => ({
          id: ids.get(n.id!)!,
          label: clip(n.label ?? "", 40),
          ...(n.detail ? { note: clip(n.detail, 240) } : {}),
          section: section.id,
          // A parent cut off by the size limit leaves its child at the root; the editor flags the second root.
          ...(n.parent && ids.has(n.parent) ? { parent: ids.get(n.parent) } : {}),
        })),
      };
    }
    case "topology":
      return {
        ...base,
        nodes: ((p.devices as Item[]) ?? [])
          .slice(0, max)
          .map((d, i) => part(d.label ?? "", i, d.detail, { x: d.x ?? 50, y: d.y ?? 50 })),
      };
    case "comparison": {
      const cols = ((p.columns as string[]) ?? []).slice(0, 3);
      const rows = ((p.rows as { label: string; values: string[] }[]) ?? []).slice(0, 4);
      const nodes = rows.flatMap((row, r) =>
        row.values.slice(0, cols.length).map((value, c) => part(value, r * 3 + c, undefined, { cell: [r, c] })),
      );
      return {
        ...base,
        rows: rows.map((r) => clip(r.label, 30)),
        cols: cols.map((c) => clip(c, 30)),
        nodes: nodes.slice(0, max),
      };
    }
    default:
      return null;
  }
}
