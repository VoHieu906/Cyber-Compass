import { gridEdgePath, layoutGrid, type GridBox, type GridMetrics } from "@/lib/graph/grid";
import { textWidth } from "@/lib/graph/text";
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

export const NODE = { width: 224, height: 132 };
/** D61: the node of a map that would not fit its box at the D59 sizes. */
export const NARROW_NODE = { width: 184, height: 132 };
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
export const EXPANDED = { pad: 12, header: NODE.height, gap: 16, detail: 84, actions: 360 };

/** The expanded node's size for a shape box: title card on top, the shape, the detail strip. */
export function expandedSize(box: { width: number; height: number }, card: { width: number } = NODE) {
  return {
    width: Math.max(box.width + 2 * EXPANDED.pad, card.width + EXPANDED.actions),
    height: EXPANDED.header + EXPANDED.gap + box.height + EXPANDED.detail,
  };
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

export type MapLayout = {
  /** Every lesson's box, and the goal's. The focused lesson's box is its whole expanded footprint; `nodeHeight` is its title card. */
  boxes: Map<string, GridBox>;
  edges: (MapEdge & { d: string; labelAt: { x: number; y: number; anchor: "middle" | "end" } })[];
  /** Lighter "contributes to" lines into the goal. */
  contributes: { from: string; d: string }[];
  bands: { label: string; y: number; height: number }[];
  /** The lesson node's size: NODE, or NARROW_NODE when the map is narrow (D61). */
  node: { width: number; height: number };
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
): MapLayout {
  const node = narrow ? NARROW_NODE : NODE;
  const metrics = narrow ? { ...METRICS, colWidth: node.width, colGap: NARROW_GAP } : METRICS;
  const { cells, bands } = placeLessons(map);
  // Room on the left for edges that bow out of the first column, and for the longest label beside such a bow.
  const sameColumnLabel = Math.max(
    0,
    ...map.edges.filter((e) => e.label && cells.get(e.from)?.col === 0 && cells.get(e.to)?.col === 0).map((e) => textWidth(e.label!, 11) + 24),
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
    : { x: grid.columns[cols].x, y: Math.max(0, (grid.height - GOAL_SIZE.height) / 2), width: GOAL_SIZE.width, height: GOAL_SIZE.height, nodeHeight: GOAL_SIZE.height };
  boxes.set(GOAL, goal);
  const width = narrow ? grid.width + metrics.colGap / 2 + 2 : grid.width - NODE.width + GOAL_SIZE.width;
  const height = narrow ? goal.y + goal.height : Math.max(grid.height, GOAL_SIZE.height);
  const intoGoal = (b: GridBox) => {
    if (!narrow) return gridEdgePath(b, goal, grid);
    const y = b.y + b.nodeHeight / 2;
    const x = b.x + b.width + metrics.colGap / 2;
    return `M ${b.x + b.width} ${y} L ${x - 8} ${y} Q ${x} ${y} ${x} ${y + 8} L ${x} ${goal.y}`;
  };

  const edges = map.edges
    .filter((e) => boxes.has(e.from) && boxes.has(e.to))
    .map((e) => {
      const a = boxes.get(e.from)!;
      const b = boxes.get(e.to)!;
      const y = (a.y + a.nodeHeight / 2 + b.y + b.nodeHeight / 2) / 2;
      const sameColumn = a.x === b.x;
      const leftBox = a.x < b.x ? a : b;
      // Between the columns for edges across them; beside the bow (gridEdgePath bows 28 to the left) within one.
      const labelAt = sameColumn
        ? { x: a.x - 10, y: Math.min(a.y, b.y) + NODE.height * 0.75, anchor: "end" as const }
        : {
            x: leftBox.x + leftBox.width + metrics.colGap / 2,
            y: Math.abs(a.x - b.x) > leftBox.width + metrics.colGap ? leftBox.y + NODE.height / 2 : y,
            anchor: "middle" as const,
          };
      return { ...e, d: gridEdgePath(a, b, grid), labelAt };
    });
  const outgoing = new Set(map.edges.filter((e) => ORDERING.has(e.type)).map((e) => e.from));
  // Every lesson contributes; with many lessons only the last of each chain draws the line.
  const contributors = map.lessons.length > 8 ? map.lessons.filter((l) => !outgoing.has(l.slug)) : map.lessons;
  const contributes = contributors.map((l) => ({ from: l.slug, d: intoGoal(boxes.get(l.slug)!) }));

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
