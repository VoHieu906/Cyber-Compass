import type { Point } from "./layout";
import type { Rect } from "./shapes/geometry";

/*
 * Orthogonal edge routing on a sparse grid (course map UI Part 3), the approach of libavoid's
 * orthogonal router without the dependency: the only grid lines are the padded obstacle edges, the
 * midlines between them (so routes run down the middle of corridors) and the edge terminals. A* on
 * that grid with a cost per bend gives few-bend, short routes that never enter a padded obstacle
 * (each edge starts and ends one pad out from its box, on that box's padded edge); a last pass moves runs that share a corridor line into lanes beside each
 * other. Pure and deterministic, like the layouts that call it.
 */

/** Outward direction from a box side: 0 up, 1 right, 2 down, 3 left. */
export type Dir = 0 | 1 | 2 | 3;
/** Where an edge meets its box: a point on the box's border and the direction out of the box. */
export type Terminal = {
  x: number;
  y: number;
  dir: Dir;
  /** How far out the route starts (default: the pad); 0 for a point already clear of every box. */
  reach?: number;
};
export type OrthoRequest = { from: Terminal; to: Terminal };
export type OrthoRoute = { points: Point[]; fallback: boolean };

const STEP = [
  [0, -1],
  [1, 0],
  [0, 1],
  [-1, 0],
] as const;

type Options = { pad?: number; bend?: number; lanes?: number[] };

export function routeOrtho(
  obstacles: Rect[],
  requests: OrthoRequest[],
  bounds: Rect,
  opts: Options = {},
): OrthoRoute[] {
  const pad = opts.pad ?? 8;
  const bend = opts.bend ?? 40;
  const boxes = obstacles.map((r) => ({
    x0: r.x - pad,
    y0: r.y - pad,
    x1: r.x + r.width + pad,
    y1: r.y + r.height + pad,
  }));
  const stub = (t: Terminal) => ({
    x: t.x + STEP[t.dir][0] * (t.reach ?? pad),
    y: t.y + STEP[t.dir][1] * (t.reach ?? pad),
  });

  // Grid lines: padded edges, the midlines between consecutive edges, the terminals and their stubs, the bounds.
  const lines = (pick: (b: (typeof boxes)[number]) => number[], ends: number[], lo: number, hi: number) => {
    const raw = [...new Set([...boxes.flatMap(pick), lo, hi].map((v) => Math.min(hi, Math.max(lo, v))))].sort(
      (a, b) => a - b,
    );
    const mids = raw.slice(1).map((v, i) => (v + raw[i]) / 2);
    return [...new Set([...raw, ...mids, ...ends])].sort((a, b) => a - b);
  };
  const terms = requests.flatMap((r) => [r.from, r.to]);
  const xs = lines(
    (b) => [b.x0, b.x1],
    terms.flatMap((t) => [t.x, stub(t).x]),
    bounds.x,
    bounds.x + bounds.width,
  );
  const ys = lines(
    (b) => [b.y0, b.y1],
    terms.flatMap((t) => [t.y, stub(t).y]),
    bounds.y,
    bounds.y + bounds.height,
  );
  const W = xs.length;
  const H = ys.length;
  const N = W * H;

  // Which obstacle covers each point and each unit segment (strictly inside; running along a padded edge is fine).
  const firstAbove = (arr: number[], v: number) => {
    let lo = 0;
    let hi = arr.length;
    while (lo < hi) {
      const m = (lo + hi) >> 1;
      if (arr[m] <= v) lo = m + 1;
      else hi = m;
    }
    return lo;
  };
  const lastBelow = (arr: number[], v: number) => firstAbove(arr, v - 1e-9) - 1;
  const point = new Int16Array(N).fill(-1);
  const hseg = new Int16Array(N).fill(-1); // from (i, j) to (i + 1, j)
  const vseg = new Int16Array(N).fill(-1); // from (i, j) to (i, j + 1)
  boxes.forEach((b, k) => {
    const i0 = firstAbove(xs, b.x0);
    const i1 = lastBelow(xs, b.x1);
    const j0 = firstAbove(ys, b.y0);
    const j1 = lastBelow(ys, b.y1);
    for (let j = j0; j <= j1; j++) for (let i = i0; i <= i1; i++) point[j * W + i] = k;
    // Segments whose middle is strictly inside: between lines on or inside the box, on a line strictly inside.
    const si0 = firstAbove(xs, b.x0 - 1e-9);
    const si1 = lastBelow(xs, b.x1 + 1e-9);
    const sj0 = firstAbove(ys, b.y0 - 1e-9);
    const sj1 = lastBelow(ys, b.y1 + 1e-9);
    for (let j = j0; j <= j1; j++) for (let i = si0; i < si1; i++) hseg[j * W + i] = k;
    for (let j = sj0; j < sj1; j++) for (let i = i0; i <= i1; i++) vseg[j * W + i] = k;
  });
  const index = (p: { x: number; y: number }) => {
    const i = xs.indexOf(p.x);
    const j = ys.indexOf(p.y);
    return i < 0 || j < 0 ? -1 : j * W + i;
  };

  // A* over (point, heading); buffers reused across searches by a generation stamp.
  const dist = new Float64Array(N * 4);
  const prev = new Int32Array(N * 4);
  const seen = new Int32Array(N * 4);
  const done = new Int32Array(N * 4);
  let gen = 0;
  const heapCost: number[] = [];
  const heapState: number[] = [];
  const push = (c: number, s: number) => {
    let i = heapCost.length;
    heapCost.push(c);
    heapState.push(s);
    while (i > 0) {
      const p = (i - 1) >> 1;
      if (heapCost[p] <= c) break;
      heapCost[i] = heapCost[p];
      heapState[i] = heapState[p];
      i = p;
    }
    heapCost[i] = c;
    heapState[i] = s;
  };
  const pop = () => {
    const top = heapState[0];
    const c = heapCost.pop()!;
    const s = heapState.pop()!;
    const n = heapCost.length;
    if (n) {
      let i = 0;
      for (;;) {
        const l = 2 * i + 1;
        if (l >= n) break;
        const m = l + 1 < n && heapCost[l + 1] < heapCost[l] ? l + 1 : l;
        if (heapCost[m] >= c) break;
        heapCost[i] = heapCost[m];
        heapState[i] = heapState[m];
        i = m;
      }
      heapCost[i] = c;
      heapState[i] = s;
    }
    return top;
  };

  const search = (start: number, startDir: Dir, goal: number, goalDir: Dir): number[] | null => {
    gen++;
    heapCost.length = 0;
    heapState.length = 0;
    const gx = xs[goal % W];
    const gy = ys[Math.floor(goal / W)];
    const h = (c: number) => {
      const dx = Math.abs(xs[c % W] - gx);
      const dy = Math.abs(ys[(c - (c % W)) / W] - gy);
      return dx + dy + (dx && dy ? bend : 0);
    };
    const s0 = start * 4 + startDir;
    dist[s0] = 0;
    seen[s0] = gen;
    prev[s0] = -1;
    push(h(start), s0);
    const free = (k: number) => k < 0;
    while (heapCost.length) {
      const st = pop();
      if (done[st] === gen) continue;
      done[st] = gen;
      const c = st >> 2;
      const dir = st & 3;
      if (c === goal && dir === goalDir) {
        const cells: number[] = [];
        for (let s = st; s !== -1; s = prev[s]) cells.push(s >> 2);
        return cells.reverse();
      }
      const i = c % W;
      const j = (c - i) / W;
      for (let d = 0; d < 4; d++) {
        if (d === (dir + 2) % 4) continue;
        const ni = i + STEP[d][0];
        const nj = j + STEP[d][1];
        if (ni < 0 || nj < 0 || ni >= W || nj >= H) continue;
        const nc = nj * W + ni;
        const seg = d === 1 ? hseg[c] : d === 3 ? hseg[nc] : d === 2 ? vseg[c] : vseg[nc];
        if (!free(seg) || (!free(point[nc]) && nc !== goal)) continue;
        const ns = nc * 4 + d;
        if (done[ns] === gen) continue;
        const cost = dist[st] + Math.abs(xs[ni] - xs[i]) + Math.abs(ys[nj] - ys[j]) + (d !== dir ? bend : 0);
        if (seen[ns] !== gen || cost < dist[ns]) {
          seen[ns] = gen;
          dist[ns] = cost;
          prev[ns] = st;
          push(cost + h(nc), ns);
        }
      }
      // Reached the goal heading the wrong way: turn in place (one bend) to enter the box straight.
      if (c === goal && dir !== goalDir) {
        const ns = c * 4 + goalDir;
        const cost = dist[st] + bend;
        if (seen[ns] !== gen || cost < dist[ns]) {
          seen[ns] = gen;
          dist[ns] = cost;
          prev[ns] = st;
          push(cost, ns);
        }
      }
    }
    return null;
  };

  const routes = requests.map((r): OrthoRoute => {
    const a = stub(r.from);
    const b = stub(r.to);
    const start = index(a);
    const goal = index(b);
    const blockedStub = (p: number) => p < 0 || point[p] >= 0;
    const cells =
      blockedStub(start) || blockedStub(goal) ? null : search(start, r.from.dir, goal, ((r.to.dir + 2) % 4) as Dir);
    if (!cells)
      return {
        points: [
          { x: r.from.x, y: r.from.y },
          { x: r.to.x, y: r.to.y },
        ],
        fallback: true,
      };
    const pts = [
      { x: r.from.x, y: r.from.y },
      ...cells.map((c) => ({ x: xs[c % W], y: ys[Math.floor(c / W)] })),
      { x: r.to.x, y: r.to.y },
    ];
    return { points: simplify(pts), fallback: false };
  });
  nudge(routes, obstacles, opts.lanes ?? [0, 4, -4, 8, -8]);
  return routes;
}

/** Drops repeated and collinear points. */
export function simplify(pts: Point[]): Point[] {
  const out: Point[] = [];
  for (const p of pts) {
    const last = out[out.length - 1];
    if (last && last.x === p.x && last.y === p.y) continue;
    if (out.length >= 2) {
      const a = out[out.length - 2];
      if ((a.x === last.x && last.x === p.x) || (a.y === last.y && last.y === p.y)) out.pop();
    }
    out.push(p);
  }
  return out;
}

/**
 * Runs that share a corridor line and overlap move into lanes beside each other, so they stay
 * visibly apart. Only inner segments move (the first and last hold the edge's ports), and a lane
 * that would touch an obstacle is skipped.
 */
function nudge(routes: OrthoRoute[], obstacles: Rect[], lanes: number[]) {
  for (const horizontal of [true, false]) {
    const segs = routes.flatMap((r, ri) =>
      r.fallback
        ? []
        : r.points.slice(1, -2).flatMap((p, k) => {
            const q = r.points[k + 2];
            const i = k + 1;
            const along = horizontal ? p.y === q.y : p.x === q.x;
            return along
              ? [
                  {
                    ri,
                    i,
                    at: horizontal ? p.y : p.x,
                    lo: Math.min(horizontal ? p.x : p.y, horizontal ? q.x : q.y),
                    hi: Math.max(horizontal ? p.x : p.y, horizontal ? q.x : q.y),
                  },
                ]
              : [];
          }),
    );
    const placed: { at: number; lo: number; hi: number }[] = [];
    for (const s of segs) {
      for (const off of lanes) {
        const at = s.at + off;
        const clash = placed.some((p) => Math.abs(p.at - at) < 3 && p.lo < s.hi && s.lo < p.hi);
        const hits = obstacles.some((o) =>
          horizontal
            ? at > o.y - 2 && at < o.y + o.height + 2 && s.lo < o.x + o.width && s.hi > o.x
            : at > o.x - 2 && at < o.x + o.width + 2 && s.lo < o.y + o.height && s.hi > o.y,
        );
        if (clash || hits) continue;
        const pts = routes[s.ri].points;
        if (off) {
          if (horizontal) pts[s.i].y = pts[s.i + 1].y = at;
          else pts[s.i].x = pts[s.i + 1].x = at;
        }
        placed.push({ at, lo: s.lo, hi: s.hi });
        break;
      }
    }
  }
}
