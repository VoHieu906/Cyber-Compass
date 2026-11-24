import type { Point } from "../layout";
import { borderPoint, centre, type Box, type Rect } from "./geometry";

/*
 * Orthogonal edge routing between boxes (course map UI fixes): every box, padded, is an obstacle;
 * an edge leaves and enters only at its own boxes, through a port on one side. Ports on a side are
 * spread out, ordered by where the other end sits, so arrowheads never share a point and edges
 * cross less. A* on a small grid, with a cost per bend and per cell another edge already uses, so
 * routes have few bends and parallel runs keep a cell apart. Pure and deterministic.
 */

export type Side = "top" | "right" | "bottom" | "left";
export type Routed = { from: string; to: string; points: Point[]; d: string; mid: Point; fallback: boolean };

const SIDES: Side[] = ["top", "right", "bottom", "left"];
/** Outward step per side, and the four move directions in the same order. */
const DIRS = [
  [0, -1],
  [1, 0],
  [0, 1],
  [-1, 0],
] as const;
const BEND = 6;
const SHARED = 4;

type Grid = {
  cols: number;
  rows: number;
  cell: number;
  blocked: Uint8Array;
  used: Uint8Array;
  /** Search buffers, reused by every search on this grid. */
  dist: Float64Array;
  prev: Int32Array;
  closed: Uint8Array;
  goal: Uint8Array;
};
type Port = { at: Point; stub: number; side: number };

function makeGrid(rects: Rect[], box: Box, pad: number, cell: number): Grid {
  const cols = Math.floor(box.width / cell) + 1;
  const rows = Math.floor(box.height / cell) + 1;
  const blocked = new Uint8Array(cols * rows);
  for (const r of rects) {
    const x0 = Math.max(0, Math.ceil((r.x - pad) / cell));
    const x1 = Math.min(cols - 1, Math.floor((r.x + r.width + pad) / cell));
    const y0 = Math.max(0, Math.ceil((r.y - pad) / cell));
    const y1 = Math.min(rows - 1, Math.floor((r.y + r.height + pad) / cell));
    for (let y = y0; y <= y1; y++) for (let x = x0; x <= x1; x++) blocked[y * cols + x] = 1;
  }
  const n = cols * rows;
  return {
    cols,
    rows,
    cell,
    blocked,
    used: new Uint8Array(n),
    dist: new Float64Array(n * 4),
    prev: new Int32Array(n * 4),
    closed: new Uint8Array(n * 4),
    goal: new Uint8Array(n),
  };
}

/** The port at `t` (0 to 1) along a side, and the first free grid point straight out from it. */
function port(g: Grid, r: Rect, side: number, t: number, pad: number): Port | null {
  const { cell, cols, rows } = g;
  const vertical = side === 1 || side === 3;
  // Snap along the side to the grid, so the step out to the stub is straight.
  const along = vertical ? r.y + t * r.height : r.x + t * r.width;
  let k = Math.round(along / cell);
  const lo = Math.ceil((vertical ? r.y : r.x) / cell);
  const hi = Math.floor((vertical ? r.y + r.height : r.x + r.width) / cell);
  if (lo > hi) return null;
  k = Math.min(hi, Math.max(lo, k));
  const out =
    side === 0
      ? Math.floor((r.y - pad) / cell) - (Number.isInteger((r.y - pad) / cell) ? 1 : 0)
      : side === 1
        ? Math.ceil((r.x + r.width + pad) / cell) + (Number.isInteger((r.x + r.width + pad) / cell) ? 1 : 0)
        : side === 2
          ? Math.ceil((r.y + r.height + pad) / cell) + (Number.isInteger((r.y + r.height + pad) / cell) ? 1 : 0)
          : Math.floor((r.x - pad) / cell) - (Number.isInteger((r.x - pad) / cell) ? 1 : 0);
  const [x, y] = vertical ? [out, k] : [k, out];
  if (x < 0 || y < 0 || x >= cols || y >= rows || g.blocked[y * cols + x]) return null;
  const at = vertical
    ? { x: side === 1 ? r.x + r.width : r.x, y: k * cell }
    : { x: k * cell, y: side === 2 ? r.y + r.height : r.y };
  return { at, stub: y * cols + x, side };
}

function swap(a: number[], s: number[], i: number, j: number) {
  const ta = a[i];
  a[i] = a[j];
  a[j] = ta;
  const ts = s[i];
  s[i] = s[j];
  s[j] = ts;
}

/** A small binary heap of [cost, state]. */
class Heap {
  private a: number[] = [];
  private s: number[] = [];
  get size() {
    return this.a.length;
  }
  push(cost: number, state: number) {
    const { a, s } = this;
    let i = a.length;
    a.push(cost);
    s.push(state);
    while (i > 0) {
      const p = (i - 1) >> 1;
      if (a[p] <= a[i]) break;
      swap(a, s, p, i);
      i = p;
    }
  }
  pop(): number {
    const { a, s } = this;
    const top = s[0];
    const lastA = a.pop()!;
    const lastS = s.pop()!;
    if (a.length) {
      a[0] = lastA;
      s[0] = lastS;
      let i = 0;
      for (;;) {
        const l = 2 * i + 1;
        const r = l + 1;
        let m = i;
        if (l < a.length && a[l] < a[m]) m = l;
        if (r < a.length && a[r] < a[m]) m = r;
        if (m === i) break;
        swap(a, s, m, i);
        i = m;
      }
    }
    return top;
  }
}

/**
 * Cheapest orthogonal path from any start port to any goal port: states are (cell, heading), a
 * turn costs BEND, a cell already used by another edge costs SHARED. Returns the grid cells, or null.
 */
function search(g: Grid, starts: Port[], goals: Port[]): { cells: number[]; start: Port; goal: Port } | null {
  const { cols, rows, blocked, used, dist, prev, closed, goal: isGoal } = g;
  dist.fill(Infinity);
  prev.fill(-1);
  closed.fill(0);
  isGoal.fill(0);
  const goalAt = new Map<number, Port[]>();
  for (const p of goals) {
    goalAt.set(p.stub, [...(goalAt.get(p.stub) ?? []), p]);
    isGoal[p.stub] = 1;
  }
  const gx = goals.map((p) => p.stub % cols);
  const gy = goals.map((p) => Math.floor(p.stub / cols));
  // Distance plus one bend when the goal is not in line: never more than the real cost.
  const h = (c: number) => {
    const x = c % cols;
    const y = (c - x) / cols;
    let best = Infinity;
    for (let i = 0; i < gx.length; i++) {
      const dx = Math.abs(gx[i] - x);
      const dy = Math.abs(gy[i] - y);
      best = Math.min(best, dx + dy + (dx && dy ? BEND : 0));
    }
    return best;
  };
  const heap = new Heap();
  const startOf = new Map<number, Port>();
  for (const p of starts) {
    const st = p.stub * 4 + p.side; // heading out of the box
    if (dist[st] <= 0) continue;
    dist[st] = 0;
    startOf.set(st, p);
    heap.push(h(p.stub), st);
  }
  while (heap.size) {
    const st = heap.pop();
    if (closed[st]) continue;
    closed[st] = 1;
    const c = st >> 2;
    const dir = st & 3;
    const here = dist[st];
    // Arriving at a goal: the last move must point into the box, else it costs one more bend.
    const options = isGoal[c] ? goalAt.get(c) : undefined;
    if (options) {
      const goal = options.find((p) => (p.side + 2) % 4 === dir);
      if (goal) {
        const cells: number[] = [];
        let s = st;
        while (s !== -1) {
          cells.push(s >> 2);
          if (prev[s] === -1) break;
          s = prev[s];
        }
        return { cells: cells.reverse(), start: startOf.get(s)!, goal };
      }
    }
    const x = c % cols;
    const y = (c - x) / cols;
    for (let d = 0; d < 4; d++) {
      if (d === (dir + 2) % 4) continue;
      const nx = x + DIRS[d][0];
      const ny = y + DIRS[d][1];
      if (nx < 0 || ny < 0 || nx >= cols || ny >= rows) continue;
      const nc = ny * cols + nx;
      if (blocked[nc] && !isGoal[nc]) continue;
      const ns = nc * 4 + d;
      if (closed[ns]) continue;
      // The outermost grid line hugs the box frame: usable, but the last choice.
      const rim = nx === 0 || ny === 0 || nx === cols - 1 || ny === rows - 1 ? 1 : 0;
      const cost = here + 1 + rim + (d !== dir ? BEND : 0) + (used[nc] ? SHARED : 0);
      if (cost < dist[ns]) {
        dist[ns] = cost;
        prev[ns] = st;
        heap.push(cost + h(nc), ns);
      }
    }
    // A turn in place at a goal cell, so a goal reached sideways can still enter straight.
    if (options && !options.some((p) => (p.side + 2) % 4 === dir))
      for (const p of options) {
        const ns = c * 4 + ((p.side + 2) % 4);
        const cost = here + BEND;
        if (cost < dist[ns]) {
          dist[ns] = cost;
          prev[ns] = st;
          heap.push(cost, ns);
        }
      }
  }
  return null;
}

/** Corner points of a grid path, collinear cells dropped. */
function corners(g: Grid, cells: number[]): Point[] {
  const pts = cells.map((c) => ({ x: (c % g.cols) * g.cell, y: Math.floor(c / g.cols) * g.cell }));
  return simplify(pts);
}

function simplify(pts: Point[]): Point[] {
  const out: Point[] = [];
  for (const p of pts) {
    if (out.length && out[out.length - 1].x === p.x && out[out.length - 1].y === p.y) continue;
    if (out.length >= 2) {
      const a = out[out.length - 2];
      const b = out[out.length - 1];
      if ((a.x === b.x && b.x === p.x) || (a.y === b.y && b.y === p.y)) out.pop();
    }
    out.push(p);
  }
  return out;
}

/** A polyline with its corners rounded (radius up to `r`), as an SVG path. */
export function roundedPath(pts: Point[], r = 6): string {
  if (pts.length < 2) return "";
  let d = `M ${pts[0].x} ${pts[0].y}`;
  for (let i = 1; i < pts.length - 1; i++) {
    const [a, b, c] = [pts[i - 1], pts[i], pts[i + 1]];
    const k = Math.min(r, Math.hypot(b.x - a.x, b.y - a.y) / 2, Math.hypot(c.x - b.x, c.y - b.y) / 2);
    const ux = Math.sign(b.x - a.x);
    const uy = Math.sign(b.y - a.y);
    const vx = Math.sign(c.x - b.x);
    const vy = Math.sign(c.y - b.y);
    d += ` L ${b.x - ux * k} ${b.y - uy * k} Q ${b.x} ${b.y} ${b.x + vx * k} ${b.y + vy * k}`;
  }
  const last = pts[pts.length - 1];
  return `${d} L ${last.x} ${last.y}`;
}

/** The middle of the longest segment: where a label sits. */
function labelPoint(pts: Point[]): Point {
  let best = { len: -1, at: pts[0] };
  for (let i = 1; i < pts.length; i++) {
    const len = Math.abs(pts[i].x - pts[i - 1].x) + Math.abs(pts[i].y - pts[i - 1].y);
    if (len > best.len) best = { len, at: { x: (pts[i].x + pts[i - 1].x) / 2, y: (pts[i].y + pts[i - 1].y) / 2 } };
  }
  return best.at;
}

/**
 * Routes `edges` between the boxes in `rects` inside `box`. First each edge picks the sides it
 * leaves and enters by (the cheapest route between side midpoints); then the ports on each side
 * are spread along it in the order of the other ends; then each edge is routed between its own
 * ports, avoiding the cells earlier edges took. An edge with no clean route is a straight line,
 * marked `fallback` for the caller to draw beneath the boxes.
 */
export function routeEdges(
  rects: Map<string, Rect>,
  edges: { from: string; to: string }[],
  box: Box,
  { pad = 6, cell = 4 }: { pad?: number; cell?: number } = {},
): Routed[] {
  const g = makeGrid([...rects.values()], box, pad, cell);
  const mids = (r: Rect) => SIDES.map((_, s) => port(g, r, s, 0.5, pad)).filter((p): p is Port => !!p);
  const valid = edges.filter((e) => rects.has(e.from) && rects.has(e.to) && e.from !== e.to);

  // 1. Sides.
  const sides = valid.map((e) => {
    const found = search(g, mids(rects.get(e.from)!), mids(rects.get(e.to)!));
    return found ? ([found.start.side, found.goal.side] as const) : null;
  });

  // 2. Ports spread along each side, ordered by the other end's centre.
  const slots = new Map<string, { edge: number; end: 0 | 1; key: number }[]>();
  valid.forEach((e, i) => {
    const s = sides[i];
    if (!s) return;
    ([0, 1] as const).forEach((end) => {
      const own = end ? e.to : e.from;
      const other = centre(rects.get(end ? e.from : e.to)!);
      const side = s[end];
      const id = `${own}:${side}`;
      slots.set(id, [...(slots.get(id) ?? []), { edge: i, end, key: side % 2 ? other.y : other.x }]);
    });
  });
  const ports = valid.map(() => [null, null] as [Port | null, Port | null]);
  for (const [id, list] of slots) {
    const [own, sideText] = [id.slice(0, id.lastIndexOf(":")), id.slice(id.lastIndexOf(":") + 1)];
    const side = Number(sideText);
    list.sort((a, b) => a.key - b.key || a.edge - b.edge || a.end - b.end);
    list.forEach((slot, k) => {
      ports[slot.edge][slot.end] = port(g, rects.get(own)!, side, (k + 1) / (list.length + 1), pad);
    });
  }

  // 3. Routes, each avoiding what the earlier ones took.
  return valid.map((e, i) => {
    const [p, q] = ports[i];
    const found = p && q ? search(g, [p], [q]) : null;
    if (!found) {
      const [a, b] = [rects.get(e.from)!, rects.get(e.to)!];
      const points = [borderPoint(a, centre(b)), borderPoint(b, centre(a))];
      return { from: e.from, to: e.to, points, d: roundedPath(points), mid: labelPoint(points), fallback: true };
    }
    for (const c of found.cells) g.used[c] = 1;
    const points = simplify([p!.at, ...corners(g, found.cells), q!.at]);
    return { from: e.from, to: e.to, points, d: roundedPath(points), mid: labelPoint(points), fallback: false };
  });
}
