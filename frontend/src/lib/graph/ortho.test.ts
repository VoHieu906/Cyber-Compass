import { describe, expect, it } from "vitest";
import type { Point } from "./layout";
import { routeOrtho, type Terminal } from "./ortho";
import type { Rect } from "./shapes/geometry";

/** Does the axis-aligned segment a-b pass through the inside of r (touching its border is fine)? */
function crosses(a: Point, b: Point, r: Rect): boolean {
  return (
    Math.min(a.x, b.x) < r.x + r.width &&
    Math.max(a.x, b.x) > r.x &&
    Math.min(a.y, b.y) < r.y + r.height &&
    Math.max(a.y, b.y) > r.y
  );
}

const right = (r: Rect, y = r.y + r.height / 2): Terminal => ({ x: r.x + r.width, y, dir: 1 });
const left = (r: Rect, y = r.y + r.height / 2): Terminal => ({ x: r.x, y, dir: 3 });

describe("orthogonal routing", () => {
  // A grid of nodes like the course map: every edge has to find its way between the others.
  const nodes: Rect[] = [];
  for (let c = 0; c < 4; c++)
    for (let r = 0; r < 4; r++) nodes.push({ x: 100 + c * 300, y: 40 + r * 110, width: 220, height: 88 });
  const at = (c: number, r: number) => nodes[c * 4 + r];
  const bounds = { x: 0, y: 0, width: 1300, height: 520 };
  const requests = [
    { from: right(at(0, 0)), to: left(at(3, 3)) }, // across the whole grid
    { from: right(at(0, 3)), to: left(at(2, 0)) },
    { from: left(at(1, 1)), to: left(at(1, 3)) }, // within one column: out to the gutter and back
    { from: right(at(3, 0)), to: right(at(0, 2), 70) }, // backwards, round the far side
    { from: right(at(0, 1), 70), to: left(at(1, 1), 90) },
  ];
  const routes = routeOrtho(nodes, requests, bounds);

  it("never passes through a node other than its own two, and only bends at right angles", () => {
    routes.forEach((route, k) => {
      expect(route.fallback, `route ${k}`).toBe(false);
      const pts = route.points;
      for (let i = 1; i < pts.length; i++) {
        expect(pts[i].x === pts[i - 1].x || pts[i].y === pts[i - 1].y, `route ${k} segment ${i}`).toBe(true);
        for (const n of nodes) expect(crosses(pts[i - 1], pts[i], n), `route ${k} segment ${i}`).toBe(false);
      }
      expect(pts[0]).toEqual({ x: requests[k].from.x, y: requests[k].from.y });
      expect(pts[pts.length - 1]).toEqual({ x: requests[k].to.x, y: requests[k].to.y });
    });
  });

  it("leaves and enters each box straight out of the side its terminal is on", () => {
    routes.forEach((route, k) => {
      const [a, b] = route.points;
      const [y, z] = route.points.slice(-2);
      expect(Math.sign(b.x - a.x), `route ${k} start`).toBe(requests[k].from.dir === 1 ? 1 : -1);
      expect(Math.sign(y.x - z.x), `route ${k} end`).toBe(requests[k].to.dir === 1 ? 1 : -1);
    });
  });

  it("takes few bends: a straight neighbour is a straight line", () => {
    expect(routes[4].points.length).toBeLessThanOrEqual(4);
  });

  it("falls back, marked, when a box is walled in", () => {
    const walled = { x: 100, y: 100, width: 100, height: 60 };
    const wall = [
      walled,
      { x: 60, y: 60, width: 180, height: 10 },
      { x: 60, y: 190, width: 180, height: 10 },
      { x: 60, y: 60, width: 10, height: 140 },
      { x: 230, y: 60, width: 10, height: 140 },
    ];
    const [route] = routeOrtho(wall, [{ from: right(walled), to: left({ x: 400, y: 100, width: 50, height: 50 }) }], {
      x: 0,
      y: 0,
      width: 500,
      height: 300,
    });
    expect(route.fallback).toBe(true);
  });

  it("routes a map's worth of edges inside a frame", () => {
    const many = nodes.flatMap((n, i) =>
      nodes.slice(i + 1, i + 3).map((m) => ({ from: right(n, n.y + 30), to: left(m, m.y + 60) })),
    );
    routeOrtho(nodes, many, bounds);
    const t0 = performance.now();
    for (let i = 0; i < 5; i++) routeOrtho(nodes, many, bounds);
    expect((performance.now() - t0) / 5).toBeLessThan(16);
  });
});
