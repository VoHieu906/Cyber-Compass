import { describe, expect, it } from "vitest";
import type { Point } from "../layout";
import type { Rect } from "./geometry";
import type { ShapeInput } from "./layouts";
import { SHAPE_BOX, SHAPES, SHAPE_REGISTRY, layoutShape } from "./registry";
import { routeEdges } from "./route";

/** Does the axis-aligned segment a-b pass through the inside of r (touching its border is fine)? */
function crosses(a: Point, b: Point, r: Rect): boolean {
  const [x0, x1] = [Math.min(a.x, b.x), Math.max(a.x, b.x)];
  const [y0, y1] = [Math.min(a.y, b.y), Math.max(a.y, b.y)];
  return x0 < r.x + r.width && x1 > r.x && y0 < r.y + r.height && y1 > r.y;
}

function points(d: string): Point[] {
  // Corners and the ends of the rounded path: every M, L and Q end point.
  const n = d.match(/-?\d+(\.\d+)?/g)!.map(Number);
  const out: Point[] = [];
  for (let i = 0; i + 1 < n.length; i += 2) out.push({ x: n[i], y: n[i + 1] });
  return out;
}

const LABELS = [
  "Router",
  "Routing table",
  "Default route",
  "Next hop",
  "Longest prefix match",
  "Static route",
  "Dynamic routing",
  "Metric",
];

function input(shape: (typeof SHAPES)[number], n: number): ShapeInput {
  const nodes: ShapeInput["nodes"] = LABELS.slice(0, n).map((label, i) => ({ id: `n${i}`, label }));
  if (shape === "tree") nodes.forEach((node, i) => i > 0 && (node.parent = `n${Math.floor((i - 1) / 2)}`));
  if (shape === "mesh") nodes.forEach((node, i) => Object.assign(node, { x: (i % 3) * 50, y: Math.floor(i / 3) * 50 }));
  if (shape === "matrix") nodes.forEach((node, i) => (node.cell = [Math.floor(i / 3), i % 3]));
  // Every node to the ones two and three further on: long relations that must go around the parts between.
  const edges = nodes.flatMap((a, i) =>
    [2, 3].flatMap((k) => (nodes[i + k] ? [{ from: a.id, to: nodes[i + k].id }] : [])),
  );
  return { nodes, edges, ...(shape === "matrix" ? { rows: ["A", "B", "C"], cols: ["x", "y", "z"] } : {}) };
}

describe("routing part relations", () => {
  it.each(SHAPES)("%s: no routed edge crosses a part that is not one of its ends", (shape) => {
    const n = Math.min(SHAPE_REGISTRY[shape].limits.max, 7);
    const layout = layoutShape(shape, input(shape, n), SHAPE_BOX.wide);
    const authored = layout.edges.filter((e) => !e.implicit);
    expect(authored.length).toBeGreaterThan(0);
    for (const e of authored.filter((e) => !e.fallback)) {
      const pts = points(e.d);
      for (const [id, r] of layout.nodes) {
        if (id === e.from || id === e.to) continue;
        for (let i = 1; i < pts.length; i++)
          expect(crosses(pts[i - 1], pts[i], r), `${shape} ${e.from}>${e.to} crosses ${id}`).toBe(false);
      }
    }
    // Most relations find a clean route; the rest are drawn beneath the parts.
    expect(authored.filter((e) => e.fallback).length).toBeLessThanOrEqual(Math.floor(authored.length / 4));
  });

  it("spreads the ends on one box apart, so no two arrowheads share a point", () => {
    const rects = new Map<string, Rect>([
      ["hub", { x: 200, y: 140, width: 120, height: 44 }],
      ["a", { x: 20, y: 20, width: 90, height: 40 }],
      ["b", { x: 400, y: 20, width: 90, height: 40 }],
      ["c", { x: 20, y: 270, width: 90, height: 40 }],
      ["d", { x: 400, y: 270, width: 90, height: 40 }],
      ["e", { x: 215, y: 20, width: 90, height: 40 }],
    ]);
    const edges = ["a", "b", "c", "d", "e"].flatMap((id) => [
      { from: id, to: "hub" },
      { from: "hub", to: id },
    ]);
    const routes = routeEdges(rects, edges, { width: 520, height: 330 });
    expect(routes.every((r) => !r.fallback)).toBe(true);
    const ends = routes.flatMap((r) => [r.points[0], r.points[r.points.length - 1]]);
    for (let i = 0; i < ends.length; i++)
      for (let j = i + 1; j < ends.length; j++)
        expect(Math.hypot(ends[i].x - ends[j].x, ends[i].y - ends[j].y), `ends ${i} and ${j}`).toBeGreaterThanOrEqual(
          8,
        );
  });

  it("orders the ends on a side by where the other end sits, so they do not cross", () => {
    const rects = new Map<string, Rect>([
      ["src", { x: 200, y: 20, width: 160, height: 40 }],
      ["left", { x: 20, y: 240, width: 100, height: 40 }],
      ["mid", { x: 230, y: 240, width: 100, height: 40 }],
      ["right", { x: 440, y: 240, width: 100, height: 40 }],
    ]);
    const routes = routeEdges(
      rects,
      ["right", "left", "mid"].map((to) => ({ from: "src", to })),
      { width: 560, height: 300 },
    );
    const startX = Object.fromEntries(routes.map((r) => [r.to, r.points[0].x]));
    expect(startX.left).toBeLessThan(startX.mid);
    expect(startX.mid).toBeLessThan(startX.right);
  });

  it("is deterministic and fast enough to run on every focus change", () => {
    const data = input("mesh", 8);
    const run = () => layoutShape("mesh", data, SHAPE_BOX.wide).edges.map((e) => e.d);
    expect(run()).toEqual(run());
    const t0 = performance.now();
    for (let i = 0; i < 10; i++) run();
    expect((performance.now() - t0) / 10).toBeLessThan(16);
  });
});
