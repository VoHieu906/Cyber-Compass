import { gridEdgePath, layoutGrid, type GridBox, type GridMetrics } from "@/lib/graph/grid";
import type { CourseMapData, MapEdge } from "./types";

/*
 * Pure layout and state of the course map (F3, D59): where each lesson sits, how the focused
 * lesson's architecture makes room, which lessons relate to the focused one, and where the arrow
 * keys go. Rendering is CourseMap's; nothing here touches the DOM, so the server and the browser
 * draw the same overview and every rule is unit-tested.
 */

export const GOAL = "@goal";

/** Relations that order lessons left to right in the automatic layout. */
const ORDERING = new Set(["requires", "builds-on", "feeds-into"]);

export const NODE = { width: 224, height: 120 };
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
/** The expanded architecture's panel below the focused lesson. */
export const FOCUS_GAP = 18;

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

export type MapLayout = {
  boxes: Map<string, GridBox>;
  edges: (MapEdge & { d: string; labelAt: { x: number; y: number; anchor: "middle" | "end" } })[];
  /** Lighter "contributes to" lines into the goal. */
  contributes: { from: string; d: string }[];
  bands: { label: string; y: number; height: number }[];
  /** Where the focused lesson's architecture is drawn, below its node. */
  focusPanel: { x: number; y: number; width: number; height: number } | null;
  width: number;
  height: number;
};

/**
 * The whole map: lesson boxes, the goal, edge paths and, in focus mode, the panel for the focused
 * lesson's architecture. Everything below the focused node moves down by the panel's height, so
 * the panel never covers a node (the transition animates that move).
 */
export function layoutCourseMap(
  map: CourseMapData,
  focused: string | null,
  panel: { width: number; height: number },
): MapLayout {
  const { cells, bands } = placeLessons(map);
  // Room on the left for edges that bow out of the first column and their labels.
  const left = bands.length ? LANE_HEADER : SIDE;
  const cols = Math.max(0, ...[...cells.values()].map((c) => c.col)) + 1;
  const rows = Math.max(0, ...[...cells.values()].map((c) => c.row)) + 1;
  const grid = layoutGrid(
    [...cells].map(([id, c]) => ({ id, row: c.row, col: c.col, children: 0 })),
    rows,
    cols + 1,
    { ...METRICS, left },
  );
  const boxes = new Map(grid.items);
  const goalHeight = GOAL_SIZE.height;
  boxes.set(GOAL, {
    x: grid.columns[cols].x,
    y: Math.max(0, (grid.height - goalHeight) / 2),
    width: GOAL_SIZE.width,
    height: goalHeight,
    nodeHeight: goalHeight,
  });

  let width = Math.max(grid.width - NODE.width + GOAL_SIZE.width, focused ? panel.width : 0);
  let height = Math.max(grid.height, goalHeight);
  let focusPanel: MapLayout["focusPanel"] = null;
  const target = focused ? boxes.get(focused) : undefined;
  if (target) {
    const top = target.y + target.nodeHeight + FOCUS_GAP;
    const shift = panel.height + FOCUS_GAP;
    for (const [id, b] of boxes) if (id !== GOAL && b.y >= top - FOCUS_GAP) boxes.set(id, { ...b, y: b.y + shift });
    width = Math.max(width, panel.width);
    focusPanel = {
      x: Math.max(0, Math.min(target.x + target.width / 2 - panel.width / 2, width - panel.width)),
      y: top,
      width: panel.width,
      height: panel.height,
    };
    height += shift;
    const goal = boxes.get(GOAL)!;
    if (goal.x < focusPanel.x + focusPanel.width && goal.y + goal.height > top)
      boxes.set(GOAL, { ...goal, y: top + shift });
    height = Math.max(height, boxes.get(GOAL)!.y + goalHeight);
  }
  const threshold = target ? target.y + target.nodeHeight : Infinity;
  const grow = target ? panel.height + FOCUS_GAP : 0;

  const shifted = { ...grid, items: boxes };
  const edges = map.edges
    .filter((e) => boxes.has(e.from) && boxes.has(e.to))
    .map((e) => {
      const a = boxes.get(e.from)!;
      const b = boxes.get(e.to)!;
      const y = (a.y + a.nodeHeight / 2 + b.y + b.nodeHeight / 2) / 2;
      const sameColumn = a.x === b.x;
      // Between the columns for edges across them; beside the bow (gridEdgePath bows 28 to the left) within one.
      const labelAt = sameColumn
        ? { x: a.x - 10, y: Math.min(a.y, b.y) + NODE.height * 0.75, anchor: "end" as const }
        : {
            x: Math.min(a.x, b.x) + NODE.width + METRICS.colGap / 2,
            y: Math.abs(a.x - b.x) > NODE.width + METRICS.colGap ? (a.x < b.x ? a : b).y + NODE.height / 2 : y,
            anchor: "middle" as const,
          };
      return { ...e, d: gridEdgePath(a, b, shifted), labelAt };
    });
  const outgoing = new Set(map.edges.filter((e) => ORDERING.has(e.type)).map((e) => e.from));
  // Every lesson contributes; with many lessons only the last of each chain draws the line.
  const contributors = map.lessons.length > 8 ? map.lessons.filter((l) => !outgoing.has(l.slug)) : map.lessons;
  const contributes = contributors.map((l) => ({
    from: l.slug,
    d: gridEdgePath(boxes.get(l.slug)!, boxes.get(GOAL)!, shifted),
  }));

  return {
    boxes,
    edges,
    contributes,
    bands: bands.map((b) => {
      const top = grid.rows[b.row].y;
      const last = grid.rows[b.row + b.rows - 1];
      const bottom = last.y + last.height;
      if (top >= threshold) return { label: b.label, y: top + grow, height: bottom - top };
      return { label: b.label, y: top, height: bottom - top + (bottom > threshold ? grow : 0) };
    }),
    focusPanel,
    width,
    height,
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
