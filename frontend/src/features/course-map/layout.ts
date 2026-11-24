import { edgeSides, gridEdgeRoute, layoutGrid, type GridBox, type GridMetrics } from "@/lib/graph/grid";
import { centre, overlaps, type Rect } from "@/lib/graph/shapes/geometry";
import { roundedPath, routeEdges } from "@/lib/graph/shapes/route";
import { routeOrtho, type OrthoRoute, type Terminal } from "@/lib/graph/ortho";
import { textWidth, wrapLabel } from "@/lib/graph/text";
import type { CourseMapData, MapEdge } from "./types";

/*
 * Pure layout and state of the course map (F3, D59): where each lesson sits, how the focused
 * lesson grows in place and the rest reflows around it, which lessons relate to the focused one, and where the arrow
 * keys go. Rendering is CourseMap's; nothing here touches the DOM, so the server and the browser
 * draw the same overview and every rule is unit-tested.
 */

export const GOAL = "@goal";

/** Relations that order lessons left to right in the automatic layout. */
const ORDERING = new Set(["requires", "builds-on", "feeds-into"]);

/** A lesson node: icon, a title of up to three lines and the status mark; the problem is in its tooltip. */
export const NODE = { width: 224, height: 92 };
/** D61: the node of a map that would not fit its box at the D59 sizes. */
export const NARROW_NODE = { width: 184, height: 92 };
const NARROW_GAP = 72;
/** The goal band under the lanes of a narrow map. */
const BAND = { gap: 48, height: 96 };
const METRICS: GridMetrics = {
  left: 0,
  top: 0,
  colWidth: NODE.width,
  colGap: 104,
  rowGap: 22,
  itemGap: 22,
  nodeHeight: NODE.height,
  childHeight: 0,
  childGap: 0,
};
const LANE_HEADER = 108;
const SIDE = 96;
const GOAL_SIZE = { width: 236, height: 132 };

/** Where the parts sit inside the expanded node, below the title card. */
/** `detail` is the strip's full height (a chosen part); `actions` the room beside the title card for the two icon actions. */
export const EXPANDED = { pad: 12, header: NODE.height, gap: 16, detail: 84, actions: 136 };

/**
 * The detail strip's height: a chosen part's label, note and links; before that the lesson's
 * problem in the lines it needs (one or two, estimated from its length, as the layout never reads
 * the DOM); with neither, only the node's bottom padding.
 */
export function detailHeight(problem: string | null, partChosen: boolean, width: number): number {
  if (partChosen) return EXPANDED.detail;
  if (!problem) return EXPANDED.pad;
  const lines = Math.min(2, Math.ceil((textWidth(problem, 14) + 96) / (width - 2 * EXPANDED.pad)));
  return 28 + 20 * lines;
}

/** The expanded node's size for a shape box: title card on top, the shape, the detail strip. */
export function expandedSize(
  box: { width: number; height: number },
  card: { width: number } = NODE,
  detail: number = EXPANDED.detail,
) {
  return {
    width: Math.max(box.width + 2 * EXPANDED.pad, card.width + EXPANDED.actions),
    height: EXPANDED.header + EXPANDED.gap + box.height + detail,
  };
}

/** The ring outside the expanded node that part links may run along on their way out. */
/** Under the 22px row gap, so the ring never reaches the lesson beyond it. */
const RING = 20;

/**
 * A focused lesson's part links (a part that leads to another lesson): inside the expanded node,
 * routed around the other parts, the title card, the actions and the detail strip, out to the
 * point of the node's edge nearest the lesson (`inner`); from there a straight line to that
 * lesson's node (`outer`, drawn beneath the lesson nodes). Everything in the expanded node's
 * coordinates; `to` is the target lesson's box.
 */
export function routePartLinks(
  panel: { width: number; height: number },
  card: { width: number; height: number },
  parts: Map<string, Rect>,
  links: { id: string; key: string; to: Rect }[],
  detail: number = EXPANDED.detail,
): { id: string; inner: string; exit: Terminal; fallback: boolean }[] {
  const shift = (r: Rect): Rect => ({ ...r, x: r.x + RING, y: r.y + RING });
  const rects = new Map<string, Rect>([...parts].map(([id, r]) => [`p:${id}`, shift(r)]));
  rects.set("card", shift({ x: 0, y: 0, ...card }));
  rects.set(
    "head",
    shift({
      x: card.width + EXPANDED.pad,
      y: 0,
      width: panel.width - card.width - EXPANDED.pad,
      height: EXPANDED.header,
    }),
  );
  if (detail > EXPANDED.pad)
    rects.set(
      "strip",
      shift({
        x: EXPANDED.pad,
        y: panel.height - detail + 4,
        width: panel.width - 2 * EXPANDED.pad,
        height: detail - 16,
      }),
    );
  // One exit per target lesson, in the ring, on the side of the node facing it.
  for (const l of links) {
    const c = centre(l.to);
    const x = Math.min(panel.width + RING / 2, Math.max(-RING / 2, c.x));
    const y = Math.min(panel.height + RING / 2, Math.max(-RING / 2, c.y));
    rects.set(`x:${l.key}`, shift({ x: x - 6, y: y - 6, width: 12, height: 12 }));
  }
  const routes = routeEdges(
    rects,
    links.map((l) => ({ from: `p:${l.id}`, to: `x:${l.key}` })),
    { width: panel.width + 2 * RING, height: panel.height + 2 * RING },
  );
  return routes.map((r, i) => {
    const pts = r.points.map((p) => ({ x: p.x - RING, y: p.y - RING }));
    // On to the exit's centre (an elbow inside the exit square), where the rest of the link starts,
    // heading straight out of the node on the side facing the lesson.
    const c = centre(links[i].to);
    const out = { x: Math.max(-c.x, c.x - panel.width, 0), y: Math.max(-c.y, c.y - panel.height, 0) };
    const x = Math.min(panel.width + RING / 2, Math.max(-RING / 2, c.x));
    const y = Math.min(panel.height + RING / 2, Math.max(-RING / 2, c.y));
    const dir: Terminal["dir"] = out.x >= out.y ? (c.x < 0 ? 3 : 1) : c.y < 0 ? 0 : 2;
    const last = pts[pts.length - 1];
    const before = pts[pts.length - 2] ?? last;
    const elbow = before.x === last.x ? { x: last.x, y } : { x, y: last.y };
    return {
      id: links[i].id,
      inner: roundedPath([...pts, elbow, { x, y }]),
      // Already clear of the node's padding: the route around the lessons starts right here.
      exit: { x, y, dir, reach: 0 },
      fallback: r.fallback,
    };
  });
}

export type Placed = { col: number; row: number };

/**
 * Grid cells per lesson. Manual: the author's cells, lanes stacked as bands of rows, lessons
 * without a place after them. Auto: columns by depth along ordering relations, rows in course order.
 */
export function placeLessons(map: Pick<CourseMapData, "layout" | "lanes" | "lessons" | "edges">): {
  cells: Map<string, Placed>;
  bands: { lane: string; label: string; row: number; rows: number }[];
} {
  const slugs = map.lessons.map((l) => l.slug);
  if (map.layout === "manual") {
    const bands: { lane: string; label: string; row: number; rows: number }[] = [];
    const cells = new Map<string, Placed>();
    let offset = 0;
    for (const lane of [...map.lanes, { id: "", label: "" }]) {
      const inLane = map.lessons.filter((l) => l.placement && (l.placement.lane ?? "") === lane.id);
      if (!inLane.length) continue;
      const rows = Math.max(...inLane.map((l) => l.placement!.row)) + 1;
      inLane.forEach((l) => cells.set(l.slug, { col: l.placement!.col, row: offset + l.placement!.row }));
      if (lane.id) bands.push({ lane: lane.id, label: lane.label, row: offset, rows });
      offset += rows;
    }
    // Lessons the author has not placed yet (a new lesson): one row after everything else.
    map.lessons.filter((l) => !cells.has(l.slug)).forEach((l, i) => cells.set(l.slug, { col: i, row: offset }));
    return { cells, bands };
  }

  const depth = new Map(slugs.map((s) => [s, 0]));
  const edges = map.edges.filter((e) => depth.has(e.from) && depth.has(e.to));
  // Longest path, capped by the lesson count so a cycle in the relations cannot run forever.
  for (let pass = 0; pass < slugs.length; pass++) {
    let changed = false;
    for (const e of edges) {
      const want = depth.get(e.from)! + (ORDERING.has(e.type) ? 1 : 0);
      if (want > depth.get(e.to)! && want < slugs.length) {
        depth.set(e.to, want);
        changed = true;
      }
    }
    if (!changed) break;
  }
  const used = new Map<number, number>();
  const cells = new Map<string, Placed>();
  for (const slug of slugs) {
    const col = depth.get(slug)!;
    cells.set(slug, { col, row: used.get(col) ?? 0 });
    used.set(col, (used.get(col) ?? 0) + 1);
  }
  return { cells, bands: [] };
}

/** One end of a line on a node side, and what orders it there: the other end's centre (y, then x). */
type End = { id: string; node: string; side: "left" | "right"; key: [number, number] };

/** Offsets within a shared channel: the middle first, then alternately below and above (the row gap is 22). */
const LANES = [0, 4, -4, 8, -8];

/**
 * Where each end sits on its node side (UI Part 2): the ends that share a side are spread evenly
 * along the node's height, in the order of the other ends, so lines leave towards their targets
 * without crossing each other at the node and no two arrowheads land on the same point.
 */
export function spreadPorts(ends: End[], boxes: Map<string, GridBox>): Map<string, number> {
  const groups = new Map<string, End[]>();
  for (const end of ends)
    groups.set(`${end.node}|${end.side}`, [...(groups.get(`${end.node}|${end.side}`) ?? []), end]);
  const ports = new Map<string, number>();
  for (const list of groups.values()) {
    const box = boxes.get(list[0].node)!;
    list.sort((a, b) => a.key[0] - b.key[0] || a.key[1] - b.key[1] || (a.id < b.id ? -1 : 1));
    list.forEach((end, k) => ports.set(end.id, box.y + (box.nodeHeight * (k + 1)) / (list.length + 1)));
  }
  return ports;
}

/**
 * Label boxes that would overlap move down a line at a time, so relation labels never sit on each
 * other: authored labels first (always shown), then the relation's type word that focus mode adds
 * (sized by the type's English name; the renderer words it).
 */
function separateLabels<
  T extends { label?: string; type: string; labelAt: { x: number; y: number; anchor: "middle" | "end" } },
>(edges: T[]) {
  const placed: { x0: number; x1: number; y: number }[] = [];
  for (const e of [...edges.filter((x) => x.label), ...edges.filter((x) => !x.label)]) {
    const w = textWidth(e.label ?? e.type, 11) + 8;
    const x0 = e.labelAt.anchor === "middle" ? e.labelAt.x - w / 2 : e.labelAt.x - w;
    let y = e.labelAt.y;
    while (placed.some((p) => x0 < p.x1 && p.x0 < x0 + w && Math.abs(p.y - y) < 14)) y += 14;
    placed.push({ x0, x1: x0 + w, y });
    e.labelAt = { ...e.labelAt, y };
  }
}

/**
 * Whether the overview routes its relations around the nodes too (UI Part 3), or keeps the D59
 * curves through the column gaps. Focus mode always routes: the expanded node is in the way.
 */
export const ROUTE_OVERVIEW = false;

type Anchored = { x: number; y: number; anchor: "middle" | "end" };

/**
 * Labels on routed lines (focus mode): each is tried along its line's level stretches, longest
 * first, then beside its upright ones, and takes the first spot clear of every node and every label
 * placed before it; authored labels go first. The type words focus mode adds are sized by the
 * type's English name (the renderer words them). With no clear spot, the longest stretch.
 */
function placeRouteLabels(
  edges: { label?: string; type: string; labelAt: Anchored; quiet?: boolean }[],
  lines: Map<number, { x: number; y: number }[]>,
  obstacles: Rect[],
) {
  const placed: Rect[] = [];
  const clear = (r: Rect) => !obstacles.some((o) => overlaps(r, o)) && !placed.some((o) => overlaps(r, o, 2));
  const order = [...edges.keys()].sort((a, b) => Number(!edges[a].label) - Number(!edges[b].label));
  for (const k of order) {
    const pts = lines.get(k);
    if (!pts) continue;
    const w = textWidth(edges[k].label ?? edges[k].type, 11) + 8;
    const segs = pts
      .slice(1)
      .map((q, i) => ({ p: pts[i], q, len: Math.abs(q.x - pts[i].x) + Math.abs(q.y - pts[i].y) }))
      .sort((a, b) => b.len - a.len);
    const tries: { at: Anchored; box: Rect }[] = [];
    for (const { p, q } of segs) {
      if (p.y === q.y) {
        // Above the line (the renderer lifts middle-anchored labels 9px), along the stretch.
        const [lo, hi] = [Math.min(p.x, q.x) + w / 2 + 4, Math.max(p.x, q.x) - w / 2 - 4];
        for (
          let x = (lo + hi) / 2, step = 0;
          lo <= hi && step < 12;
          step++, x = (lo + hi) / 2 + (step % 2 ? 1 : -1) * Math.ceil(step / 2) * 12
        )
          if (x >= lo && x <= hi)
            tries.push({
              at: { x, y: p.y, anchor: "middle" },
              box: { x: x - w / 2, y: p.y - 16, width: w, height: 14 },
            });
      } else {
        // Left of an upright stretch, along it.
        const [lo, hi] = [Math.min(p.y, q.y) + 10, Math.max(p.y, q.y) - 10];
        for (
          let y = (lo + hi) / 2, step = 0;
          lo <= hi && step < 12;
          step++, y = (lo + hi) / 2 + (step % 2 ? 1 : -1) * Math.ceil(step / 2) * 12
        )
          if (y >= lo && y <= hi)
            tries.push({
              at: { x: p.x - 5, y, anchor: "end" },
              box: { x: p.x - 5 - w, y: y - 7, width: w, height: 14 },
            });
      }
    }
    const spot = tries.find((t) => clear(t.box)) ?? (edges[k].label ? tries[0] : undefined);
    // No clear room for focus mode's type word: leave it out (the relation's tooltip still names it).
    if (!spot) {
      if (!edges[k].label) edges[k].quiet = true;
      continue;
    }
    edges[k].labelAt = spot.at;
    placed.push(spot.box);
  }
}

export type MapLayout = {
  /** Every lesson's box, and the goal's. The focused lesson's box is its whole expanded footprint; `nodeHeight` is its title card. */
  boxes: Map<string, GridBox>;
  /** `quiet`: no clear room for focus mode's type word, so it is left out. */
  edges: (MapEdge & { d: string; labelAt: { x: number; y: number; anchor: "middle" | "end" }; quiet?: boolean })[];
  /** Lighter "contributes to" lines into the goal. */
  contributes: { from: string; d: string }[];
  bands: { label: string; y: number; height: number }[];
  /** The lesson node's size: NODE, or NARROW_NODE when the map is narrow (D61). */
  node: { width: number; height: number };
  /** Relations or goal lines with no clean route (drawn as before, beneath the nodes). */
  fallbacks: string[];
  /** Focus mode: where each part link lands on its lesson, spread with that side's other ends. */
  partPorts: Map<string, Terminal>;
  /** Routes the rest of each part link, from the expanded node's edge to its lesson, around every node. */
  routeLinks: (links: { from: Terminal; terminal: Terminal }[]) => OrthoRoute[];
  width: number;
  height: number;
};

/**
 * The whole map: lesson boxes, the goal and edge paths. In focus mode the focused lesson's own box
 * grows in place to `expanded` (its title card stays at the top left, its architecture fills the
 * rest), and the grid reflows around it: its column widens, so later columns move right, and its
 * row grows, so later rows move down (layoutGrid). No zoom: everything keeps its size.
 */
export function layoutCourseMap(
  map: CourseMapData,
  focused: string | null,
  expanded: { width: number; height: number },
  narrow = false,
  /** `route: false` for size-only layouts; overview routing follows ROUTE_OVERVIEW. */
  opts: { route?: boolean } = {},
): MapLayout {
  const node = narrow ? NARROW_NODE : NODE;
  const metrics = narrow ? { ...METRICS, colWidth: node.width, colGap: NARROW_GAP } : METRICS;
  const { cells, bands } = placeLessons(map);
  // Room on the left for edges that bow out of the first column, and for the longest label beside such a bow.
  const sameColumnLabel = Math.max(
    0,
    ...map.edges
      .filter((e) => e.label && cells.get(e.from)?.col === 0 && cells.get(e.to)?.col === 0)
      .map((e) => textWidth(e.label!, 11) + 36),
  );
  const left = Math.max(bands.length ? LANE_HEADER : SIDE, sameColumnLabel);
  const cols = Math.max(0, ...[...cells.values()].map((c) => c.col)) + 1;
  const rows = Math.max(0, ...[...cells.values()].map((c) => c.row)) + 1;
  const grid = layoutGrid(
    [...cells].map(([id, c]) => ({ id, row: c.row, col: c.col, children: 0, ...(id === focused ? expanded : {}) })),
    rows,
    narrow ? cols : cols + 1,
    { ...metrics, left },
  );
  const boxes = new Map(grid.items);
  // Narrow (D61): the goal is a band under the lessons, and the lines into it run down the gutter right of each column.
  const goal = narrow
    ? { x: left, y: grid.height + BAND.gap, width: grid.width - left, height: BAND.height, nodeHeight: BAND.height }
    : {
        x: grid.columns[cols].x,
        y: Math.max(0, (grid.height - GOAL_SIZE.height) / 2),
        width: GOAL_SIZE.width,
        height: GOAL_SIZE.height,
        nodeHeight: GOAL_SIZE.height,
      };
  boxes.set(GOAL, goal);
  const width = narrow ? grid.width + metrics.colGap / 2 + 2 : grid.width - NODE.width + GOAL_SIZE.width;
  const height = narrow ? goal.y + goal.height : Math.max(grid.height, GOAL_SIZE.height);
  const placedEdges = map.edges.filter((e) => boxes.has(e.from) && boxes.has(e.to));
  const outgoing = new Set(map.edges.filter((e) => ORDERING.has(e.type)).map((e) => e.from));
  // Every lesson contributes; with many lessons only the last of each chain draws the line.
  const contributors = map.lessons.length > 8 ? map.lessons.filter((l) => !outgoing.has(l.slug)) : map.lessons;

  // Every end on every node side, relations and the lines into the goal alike, spread along it.
  const mid = (b: GridBox): [number, number] => [b.y + b.nodeHeight / 2, b.x];
  const ends: End[] = placedEdges.flatMap((e, i) => {
    const [a, b] = [boxes.get(e.from)!, boxes.get(e.to)!];
    const [sa, sb] = edgeSides(a, b);
    return [
      { id: `${i}>`, node: e.from, side: sa, key: mid(b) },
      { id: `${i}<`, node: e.to, side: sb, key: mid(a) },
    ];
  });
  // Focus mode: the focused lesson's part links land on their lessons, on the side facing the expanded node.
  const frame = focused ? boxes.get(focused) : undefined;
  const partLinks = frame
    ? (map.lessons.find((l) => l.slug === focused)?.nodes ?? []).filter(
        (n) => n.lesson && n.lesson !== focused && boxes.has(n.lesson),
      )
    : [];
  for (const n of partLinks) {
    const target = boxes.get(n.lesson!)!;
    ends.push({ id: `part:${n.id}`, node: n.lesson!, side: edgeSides(frame!, target)[1], key: mid(frame!) });
  }
  for (const l of contributors) {
    // Narrow: the line turns down the gutter, so it takes the lowest place on the side.
    ends.push({ id: `goal:${l.slug}>`, node: l.slug, side: "right", key: narrow ? [Infinity, 0] : mid(goal) });
    if (!narrow) ends.push({ id: `goal:${l.slug}<`, node: GOAL, side: "left", key: mid(boxes.get(l.slug)!) });
  }
  const ports = spreadPorts(ends, boxes);
  const taken = new Map<string, number>();
  const lane = (col: number, y: number) => {
    const n = taken.get(`${col}:${y}`) ?? 0;
    taken.set(`${col}:${y}`, n + 1);
    return LANES[n % LANES.length];
  };

  const intoGoal = (slug: string) => {
    const b = boxes.get(slug)!;
    const fy = ports.get(`goal:${slug}>`)!;
    if (!narrow) return gridEdgeRoute(b, goal, grid, 28, { fy, ty: ports.get(`goal:${slug}<`), lane }).d;
    const x = b.x + b.width + metrics.colGap / 2;
    return `M ${b.x + b.width} ${fy} L ${x - 8} ${fy} Q ${x} ${fy} ${x} ${fy + 8} L ${x} ${goal.y}`;
  };

  const edges = placedEdges.map((e, i) => {
    const route = gridEdgeRoute(boxes.get(e.from)!, boxes.get(e.to)!, grid, 28, {
      fy: ports.get(`${i}>`),
      ty: ports.get(`${i}<`),
      lane,
    });
    // Across columns: above the middle of the first stretch; within one: left of the bow.
    const sameColumn = boxes.get(e.from)!.x === boxes.get(e.to)!.x;
    const labelAt = sameColumn
      ? { x: route.mid.x - 6, y: route.mid.y, anchor: "end" as const }
      : { x: route.mid.x, y: route.mid.y, anchor: "middle" as const };
    return { ...e, d: route.d, labelAt };
  });
  const contributes = contributors.map((l) => ({ from: l.slug, d: intoGoal(l.slug) }));

  // UI Part 3: route every relation (and the goal lines, beside the goal) around every node.
  const terminal = (slug: string, id: string, side: "left" | "right"): Terminal => {
    const b = boxes.get(slug)!;
    return side === "left" ? { x: b.x, y: ports.get(id)!, dir: 3 } : { x: b.x + b.width, y: ports.get(id)!, dir: 1 };
  };
  const order = [...boxes.keys()];
  const obstacles: Rect[] = [
    ...order.map((k) => {
      const b = boxes.get(k)!;
      return { x: b.x, y: b.y, width: b.width, height: b.height };
    }),
    // Lane names on the left are text the lines must not run through.
    ...bands.map((b) => ({
      x: 8,
      y: grid.rows[b.row].y,
      width: LANE_HEADER - 16,
      height: wrapLabel(b.label, 12, 3).length * 16 + 6,
    })),
  ];
  const bounds = { x: 0, y: -16, width, height: height + 32 };
  const fallbacks: string[] = [];
  const routed = opts.route ?? (focused ? true : ROUTE_OVERVIEW);
  if (routed) {
    const requests = placedEdges.map((e, i) => {
      const [sa, sb] = edgeSides(boxes.get(e.from)!, boxes.get(e.to)!);
      return {
        from: terminal(e.from, `${i}>`, sa),
        to: terminal(e.to, `${i}<`, sb),
      };
    });
    const goalLines = narrow
      ? []
      : contributors.map((l) => ({
          from: terminal(l.slug, `goal:${l.slug}>`, "right"),
          to: terminal(GOAL, `goal:${l.slug}<`, "left"),
        }));
    const routes = routeOrtho(obstacles, [...requests, ...goalLines], bounds);
    const lines = new Map<number, { x: number; y: number }[]>();
    edges.forEach((e, i) => {
      const r = routes[i];
      if (r.fallback) return void fallbacks.push(`${e.from} -> ${e.to}`);
      e.d = roundedPath(r.points, 8);
      lines.set(i, r.points);
    });
    placeRouteLabels(edges, lines, obstacles);
    contributes.forEach((c, k) => {
      const r = routes[requests.length + k];
      if (r && !r.fallback) c.d = roundedPath(r.points, 8);
      else if (r) fallbacks.push(`${c.from} -> goal`);
    });
  }
  if (!routed) separateLabels(edges);
  const partPorts = new Map(
    partLinks.map((n) => {
      const side = edgeSides(frame!, boxes.get(n.lesson!)!)[1];
      return [n.id, terminal(n.lesson!, `part:${n.id}`, side)] as const;
    }),
  );
  const routeLinks = (links: { from: Terminal; terminal: Terminal }[]) =>
    routeOrtho(
      obstacles,
      links.map((l) => ({ from: l.from, to: l.terminal })),
      bounds,
    );

  return {
    boxes,
    edges,
    contributes,
    bands: bands.map((b) => {
      const top = grid.rows[b.row].y;
      const last = grid.rows[b.row + b.rows - 1];
      return { label: b.label, y: top, height: last.y + last.height - top };
    }),
    node,
    width,
    height,
    fallbacks,
    partPorts,
    routeLinks,
  };
}

/** Lessons directly related to the focused one (either direction), plus lessons its sub-nodes link to. */
export function relatedTo(map: CourseMapData, focused: string | null): Set<string> {
  const related = new Set<string>();
  if (!focused) return related;
  for (const e of map.edges) {
    if (e.from === focused) related.add(e.to);
    if (e.to === focused) related.add(e.from);
  }
  for (const n of map.lessons.find((l) => l.slug === focused)?.nodes ?? []) if (n.lesson) related.add(n.lesson);
  related.delete(focused);
  return related;
}

export type NodeState = "focused" | "related" | "dimmed" | "normal";

export function nodeState(map: CourseMapData, focused: string | null, slug: string): NodeState {
  if (!focused) return "normal";
  if (slug === focused) return "focused";
  return relatedTo(map, focused).has(slug) ? "related" : "dimmed";
}

/**
 * Where an arrow key goes from a lesson: → follows a relation forward (the goal after the last),
 * ← follows one backward, ↓ and ↑ move through the lessons in course order.
 */
export function moveFrom(
  map: CourseMapData,
  from: string,
  key: "ArrowRight" | "ArrowLeft" | "ArrowDown" | "ArrowUp",
): string {
  const order = map.lessons.map((l) => l.slug);
  const at = order.indexOf(from);
  const byOrder = (a: string, b: string) => order.indexOf(a) - order.indexOf(b);
  if (from === GOAL) return key === "ArrowLeft" || key === "ArrowUp" ? (order[order.length - 1] ?? GOAL) : GOAL;
  switch (key) {
    case "ArrowRight":
      return (
        map.edges
          .filter((e) => e.from === from)
          .map((e) => e.to)
          .sort(byOrder)[0] ??
        order[at + 1] ??
        GOAL
      );
    case "ArrowLeft":
      return (
        map.edges
          .filter((e) => e.to === from)
          .map((e) => e.from)
          .sort(byOrder)
          .at(-1) ??
        order[at - 1] ??
        from
      );
    case "ArrowDown":
      return order[at + 1] ?? GOAL;
    case "ArrowUp":
      return order[at - 1] ?? from;
  }
}
