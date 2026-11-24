import { describe, expect, it } from "vitest";
import { overlaps } from "./geometry";
import type { ShapeInput } from "./layouts";
import { SHAPE_BOX, SHAPE_REGISTRY, SHAPES, layoutShape, type ShapeId } from "./registry";
import { fitBox } from "./sizes";

/* Every shape, from one node to its maximum, in both footprints: inside the box, no two nodes touching, deterministic. */

const LABELS = [
  "Password",
  "Salt per user",
  "Slow key derivation function",
  "Stored digest",
  "Login check",
  "Rate limiting at the edge",
  "Audit trail",
  "Rotation",
  "Breach response playbook",
  "Users",
  "Keys",
  "Certificates",
];

function input(shape: ShapeId, n: number, tree: "wide" | "chain" = "wide"): ShapeInput {
  const nodes: ShapeInput["nodes"] = LABELS.slice(0, n).map((label, i) => ({ id: `n${i}`, label }));
  if (shape === "tree")
    nodes.forEach((node, i) => i > 0 && (node.parent = tree === "chain" ? `n${i - 1}` : `n${Math.floor((i - 1) / 3)}`));
  if (shape === "mesh") nodes.forEach((node, i) => Object.assign(node, { x: (i % 4) * 33, y: Math.floor(i / 4) * 50 }));
  const rows = ["Low", "Medium", "High", "Critical"].slice(0, Math.ceil(n / 3));
  if (shape === "matrix") nodes.forEach((node, i) => (node.cell = [Math.floor(i / 3), i % 3]));
  return { nodes, edges: [], ...(shape === "matrix" ? { rows, cols: ["Rare", "Possible", "Likely"] } : {}) };
}

function expectClean(shape: ShapeId, data: ShapeInput, box: { width: number; height: number }) {
  const layout = layoutShape(shape, data, box);
  const rects = [...layout.nodes.entries()];
  expect(rects).toHaveLength(data.nodes.length);
  for (const [id, r] of rects) {
    expect(r.x, `${shape} ${id} left`).toBeGreaterThanOrEqual(0);
    expect(r.y, `${shape} ${id} top`).toBeGreaterThanOrEqual(0);
    expect(r.x + r.width, `${shape} ${id} right`).toBeLessThanOrEqual(box.width);
    expect(r.y + r.height, `${shape} ${id} bottom`).toBeLessThanOrEqual(box.height);
    expect(r.lines.length).toBeGreaterThan(0);
  }
  for (let i = 0; i < rects.length; i++)
    for (let j = i + 1; j < rects.length; j++)
      expect(overlaps(rects[i][1], rects[j][1]), `${shape}: ${rects[i][0]} and ${rects[j][0]} overlap`).toBe(false);
  return layout;
}

describe.each(SHAPES)("%s", (shape) => {
  const { max } = SHAPE_REGISTRY[shape].limits;
  it.each(Object.entries(SHAPE_BOX))(
    "lays out 1 to the maximum nodes inside the %s footprint without overlaps",
    (_, box) => {
      for (let n = 1; n <= max; n++) expectClean(shape, input(shape, n), box);
    },
  );

  it("fits inside the box it asks for, at every size", () => {
    for (let n = 1; n <= max; n++) {
      const data = input(shape, n);
      for (const box of Object.values(SHAPE_BOX))
        expectClean(shape, data, fitBox(shape, n, data.rows?.length ?? 0, box));
    }
  });

  it("is deterministic and has a glyph", () => {
    const data = input(shape, max);
    expect(JSON.stringify([...layoutShape(shape, data, SHAPE_BOX.wide).nodes])).toBe(
      JSON.stringify([...layoutShape(shape, data, SHAPE_BOX.wide).nodes]),
    );
    expect(SHAPE_REGISTRY[shape].glyph).toMatch(/^M/);
  });
});

describe("shape details", () => {
  it("draws the order of pipelines and cycles, closing the cycle", () => {
    const pipe = layoutShape("pipeline", input("pipeline", 4), SHAPE_BOX.wide).edges.filter((e) => e.implicit);
    const loop = layoutShape("cycle", input("cycle", 4), SHAPE_BOX.wide).edges.filter((e) => e.implicit);
    expect(pipe.map((e) => `${e.from}>${e.to}`)).toEqual(["n0>n1", "n1>n2", "n2>n3"]);
    expect(loop.map((e) => `${e.from}>${e.to}`)).toEqual(["n0>n1", "n1>n2", "n2>n3", "n3>n0"]);
  });

  it("puts the first stack item at the bottom and the first queue item at the front", () => {
    const s = layoutShape("stack", input("stack", 3), SHAPE_BOX.wide).nodes;
    const q = layoutShape("queue", input("queue", 3), SHAPE_BOX.wide);
    expect(s.get("n0")!.y).toBeGreaterThan(s.get("n2")!.y);
    expect(q.nodes.get("n0")!.x).toBeLessThan(q.nodes.get("n2")!.x);
    expect(q.decorations.filter((d) => d.kind === "marker").map((d) => d.kind === "marker" && d.key)).toEqual([
      "front",
      "back",
    ]);
  });

  describe("queue frame", () => {
    const frame = (n: number) => {
      const box = fitBox("queue", n, 0, SHAPE_BOX.wide);
      const layout = layoutShape("queue", input("queue", n), box);
      const paths = layout.decorations.flatMap((d) => (d.kind === "path" ? [d.d] : []));
      const markers = layout.decorations.flatMap((d) => (d.kind === "marker" ? [d] : []));
      // The coordinates of every point a path passes through (arc radii and flags dropped).
      const xs = paths.flatMap((d) =>
        [...d.matchAll(/[MLH] ([\d.-]+)|A [\d.]+ [\d.]+ 0 0 1 ([\d.-]+)/g)].map((m) => +(m[1] ?? m[2])),
      );
      return { box, layout, paths, markers, xs };
    };
    // A vee is three points; a rail is a straight run (one row) or a bend (two rows).
    const vees = (paths: string[]) => paths.filter((d) => /^M [\d.-]+ [\d.-]+ L [\d.-]+ [\d.-]+ L /.test(d));

    it("puts one row in a tube open at both ends, vees pointing to the front", () => {
      const { box, layout, paths, markers, xs } = frame(4);
      const rails = paths.filter((d) => /^M [\d.-]+ [\d.-]+ H [\d.-]+$/.test(d));
      expect(rails).toHaveLength(2);
      const ys = rails.map((d) => +d.split(" ")[2]);
      for (const r of layout.nodes.values()) {
        expect(Math.min(...ys)).toBeLessThan(r.y);
        expect(Math.max(...ys)).toBeGreaterThan(r.y + r.height);
      }
      // 3 gaps, a vee on each rail; each opens to the right, so its tip (the middle point) is leftmost.
      const v = vees(paths);
      expect(v).toHaveLength(6);
      for (const d of v) {
        const [end, tip] = [...d.matchAll(/[ML] ([\d.-]+)/g)].map((m) => +m[1]);
        expect(tip).toBeLessThan(end);
      }
      expect(paths).toHaveLength(rails.length + v.length);
      expect(Math.min(...xs)).toBeGreaterThanOrEqual(0);
      expect(Math.max(...xs)).toBeLessThanOrEqual(box.width);
      expect(markers.map((m) => [m.key, m.anchor])).toEqual([
        ["front", "start"],
        ["back", "end"],
      ]);
    });

    it("bends the tube round to a second row that runs back towards the entry", () => {
      const n = SHAPE_REGISTRY.queue.limits.max;
      expect(n).toBeGreaterThan(5);
      const { box, layout, paths, markers, xs } = frame(n);
      const at = (i: number) => layout.nodes.get(`n${i}`)!;
      const perRow = Math.ceil(n / 2);
      // First row left to right, second row right to left, below it.
      expect(at(0).x).toBeLessThan(at(perRow - 1).x);
      expect(at(perRow).x).toBeGreaterThan(at(n - 1).x);
      expect(at(perRow).y).toBeGreaterThan(at(0).y + at(0).height);
      const bends = paths.filter((d) => d.includes(" A "));
      expect(bends).toHaveLength(2);
      expect(paths).toHaveLength(bends.length + vees(paths).length);
      expect(Math.min(...xs)).toBeGreaterThanOrEqual(0);
      expect(Math.max(...xs)).toBeLessThanOrEqual(box.width);
      // The bend's outer wall clears the last slot of the first row.
      expect(Math.max(...xs)).toBeGreaterThan(at(perRow - 1).x + at(perRow - 1).width);
      const back = markers.find((m) => m.key === "back")!;
      expect(back.y).toBeGreaterThan(at(n - 1).y + at(n - 1).height);
      expect(back.y + 6).toBeLessThanOrEqual(box.height);
    });
  });

  it("nests onion rings from the core outwards and spokes from the hub centre", () => {
    const rings = layoutShape("onion", input("onion", 4), SHAPE_BOX.wide).decorations.filter(
      (d) => d.kind === "ellipse",
    );
    expect(rings.map((r) => r.kind === "ellipse" && r.ry)).toEqual(
      [...rings.map((r) => r.kind === "ellipse" && r.ry)].sort((a, b) => Number(a) - Number(b)),
    );
    expect(layoutShape("hub", input("hub", 5), SHAPE_BOX.wide).edges.every((e) => e.from === "n0" && !e.directed)).toBe(
      true,
    );
  });

  it("keeps a deep tree inside the box and parents above children", () => {
    const chain = expectClean("tree", input("tree", 12, "chain"), SHAPE_BOX.compact);
    expect(chain.nodes.get("n0")!.y).toBeLessThan(chain.nodes.get("n11")!.y);
  });

  it("separates mesh nodes an author placed on top of each other", () => {
    expectClean(
      "mesh",
      {
        nodes: [
          { id: "a", label: "Alpha", x: 50, y: 50 },
          { id: "b", label: "Beta", x: 50, y: 50 },
          { id: "c", label: "Gamma", x: 52, y: 48 },
        ],
        edges: [],
      },
      SHAPE_BOX.wide,
    );
  });

  it("labels authored sub-edges at their middle and routes layer edges beside the bands", () => {
    const data = { ...input("layers", 4), edges: [{ from: "n0", to: "n3", label: "depends on" }] };
    const layout = layoutShape("layers", data, SHAPE_BOX.wide);
    const [edge] = layout.edges;
    expect(edge.labelAt).toBeDefined();
    // Out of the right side of the first band, down the gutter, into the right side of the last.
    const right = layout.nodes.get("n0")!.x + layout.nodes.get("n0")!.width;
    expect(edge.labelAt!.x).toBeGreaterThan(right);
    expect(edge.fallback).toBe(false);
  });
});
