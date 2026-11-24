/**
 * A pure grid layout for graphs whose positions come from the data (stages, levels, domains),
 * not from an automatic layout (ARCHITECTURE D27). Items sit in (row, column) cells and stack
 * inside a cell in input order; an item may carry child rows (expanded lessons) below its node.
 * Rendering is the caller's: this file only returns coordinates and edge paths.
 */

export type GridItem = {
  id: string;
  row: number;
  col: number;
  /** Child rows drawn under the node (0 when collapsed). */
  children: number;
  /** Overrides the item's whole size (an expanded node, F3 course map); its column widens and its row grows to fit, so the other items reflow around it. */
  width?: number;
  height?: number;
};

export type GridMetrics = {
  /** Space before the first column (row headers). */
  left: number;
  /** Space above the first row (column headers). */
  top: number;
  colWidth: number;
  colGap: number;
  rowGap: number;
  /** Gap between items stacked in one cell. */
  itemGap: number;
  nodeHeight: number;
  childHeight: number;
  /** Space between the node and its first child row. */
  childGap: number;
  /** Minimum row height, so empty rows still show their header. */
  minRowHeight?: number;
};

export type GridBox = { x: number; y: number; width: number; height: number; nodeHeight: number };

export type GridLayout = {
  items: Map<string, GridBox>;
  columns: { x: number }[];
  rows: { y: number; height: number }[];
  width: number;
  height: number;
};

export function itemHeight(item: Pick<GridItem, "children" | "height">, m: GridMetrics): number {
  return item.height ?? m.nodeHeight + (item.children > 0 ? m.childGap + item.children * m.childHeight : 0);
}

export function layoutGrid(items: GridItem[], rowCount: number, colCount: number, m: GridMetrics): GridLayout {
  const cellHeight = new Map<string, number>();
  for (const item of items) {
    const key = `${item.row}:${item.col}`;
    const used = cellHeight.get(key);
    cellHeight.set(key, (used === undefined ? 0 : used + m.itemGap) + itemHeight(item, m));
  }

  const colWidths = Array.from({ length: colCount }, (_, c) =>
    Math.max(m.colWidth, ...items.filter((i) => i.col === c).map((i) => i.width ?? 0)),
  );
  const columns: { x: number }[] = [];
  let x = m.left;
  for (const w of colWidths) {
    columns.push({ x });
    x += w + m.colGap;
  }
  const rows: { y: number; height: number }[] = [];
  let y = m.top;
  for (let r = 0; r < rowCount; r++) {
    let height = m.minRowHeight ?? m.nodeHeight;
    for (let c = 0; c < colCount; c++) height = Math.max(height, cellHeight.get(`${r}:${c}`) ?? 0);
    rows.push({ y, height });
    y += height + m.rowGap;
  }

  const placed = new Map<string, GridBox>();
  const cursor = new Map<string, number>();
  for (const item of items) {
    const key = `${item.row}:${item.col}`;
    const offset = cursor.get(key) ?? 0;
    const height = itemHeight(item, m);
    placed.set(item.id, {
      x: columns[item.col].x,
      y: rows[item.row].y + offset,
      width: item.width ?? m.colWidth,
      height,
      nodeHeight: m.nodeHeight,
    });
    cursor.set(key, offset + height + m.itemGap);
  }

  return {
    items: placed,
    columns,
    rows,
    width: m.left + colWidths.reduce((a, b) => a + b, 0) + Math.max(0, colCount - 1) * m.colGap,
    height: rowCount ? y - m.rowGap : m.top,
  };
}

/** A horizontal cubic from one point to another, with flat tangents at both ends. */
function curve(x1: number, y1: number, x2: number, y2: number): string {
  const mid = (x1 + x2) / 2;
  return `C ${mid} ${y1}, ${mid} ${y2}, ${x2} ${y2}`;
}

/**
 * Free horizontal channels through a column: above its first item, in each gap between items,
 * and below its last. An edge that skips a column runs through one of these, never behind a node.
 */
function channels(layout: GridLayout, col: number): number[] {
  const x = layout.columns[col].x;
  const spans = [...layout.items.values()]
    .filter((b) => b.x === x)
    .map((b) => [b.y, b.y + b.height] as const)
    .sort((a, b) => a[0] - b[0]);
  if (!spans.length) return [];
  const free = [spans[0][0] - 8];
  for (let i = 1; i < spans.length; i++)
    if (spans[i][0] > spans[i - 1][1]) free.push((spans[i - 1][1] + spans[i][0]) / 2);
  free.push(spans[spans.length - 1][1] + 8);
  return free;
}

export type EdgeEnds = {
  /** Where the edge leaves `from` and enters `to` along their sides (default: the middle). */
  fy?: number;
  ty?: number;
  /** An offset for this edge's run through a column's channel, so edges sharing it stay apart. */
  lane?: (col: number, y: number) => number;
};

/** The side of each end an edge uses: forward edges leave right and enter left, backward ones the reverse, edges within a column use the left side at both ends. */
export function edgeSides(from: GridBox, to: GridBox): ["left" | "right", "left" | "right"] {
  if (to.x >= from.x + from.width) return ["right", "left"];
  if (to.x + to.width <= from.x) return ["left", "right"];
  return ["left", "left"];
}

/**
 * An edge between two nodes (the node part of each box, not its children). Forward edges run
 * from the right side to the left side, and through a free channel of every column they skip;
 * backward edges run the other way; edges inside one column bow out into the gutter on the
 * left, so they never cross the nodes between them. Pass the layout to route around nodes.
 */
export function gridEdgePath(from: GridBox, to: GridBox, layout?: GridLayout, bow = 28, ends: EdgeEnds = {}): string {
  return gridEdgeRoute(from, to, layout, bow, ends).d;
}

/** gridEdgePath, and where its label sits: the middle of its first stretch from the left (the gutter beside a bow). */
export function gridEdgeRoute(
  from: GridBox,
  to: GridBox,
  layout?: GridLayout,
  bow = 28,
  ends: EdgeEnds = {},
): { d: string; mid: { x: number; y: number } } {
  const fy = ends.fy ?? from.y + from.nodeHeight / 2;
  const ty = ends.ty ?? to.y + to.nodeHeight / 2;
  const forward = to.x >= from.x + from.width;
  const backward = to.x + to.width <= from.x;
  if (!forward && !backward)
    return {
      d: `M ${from.x} ${fy} C ${from.x - bow} ${fy}, ${to.x - bow} ${ty}, ${to.x} ${ty}`,
      mid: { x: from.x - bow * 0.75, y: (fy + ty) / 2 },
    };

  const [left, right, ly, ry] = forward ? [from, to, fy, ty] : [to, from, ty, fy];
  // Segments left to right: leave the left node, cross each skipped column straight along a free
  // channel, enter the right node. Curves join them with flat tangents.
  const segments: { from: [number, number]; to: [number, number]; straight: boolean }[] = [];
  let at: [number, number] = [left.x + left.width, ly];
  for (const [i, column] of (layout?.columns ?? []).entries()) {
    if (column.x <= left.x || column.x >= right.x) continue;
    const free = channels(layout!, i);
    if (!free.length) continue;
    const target = (at[1] + ry) / 2;
    const channel = free.reduce((best, ch) => (Math.abs(ch - target) < Math.abs(best - target) ? ch : best));
    const y = channel + (ends.lane?.(i, channel) ?? 0);
    // The skipped column's own width: an expanded item can make it wider than the nodes at either end.
    const width = Math.max(...[...layout!.items.values()].filter((b) => b.x === column.x).map((b) => b.width));
    segments.push(
      { from: at, to: [column.x, y], straight: false },
      { from: [column.x, y], to: [column.x + width, y], straight: true },
    );
    at = [column.x + width, y];
  }
  segments.push({ from: at, to: [right.x, ry], straight: false });
  const first = segments[0];
  const mid = { x: (first.from[0] + first.to[0]) / 2, y: (first.from[1] + first.to[1]) / 2 };

  // The path ends at the target, where the arrowhead goes: backward edges are walked right to left.
  const ordered = forward
    ? segments
    : segments.reverse().map((sg) => ({ from: sg.to, to: sg.from, straight: sg.straight }));
  let d = `M ${ordered[0].from[0]} ${ordered[0].from[1]}`;
  for (const sg of ordered)
    d += sg.straight ? ` L ${sg.to[0]} ${sg.to[1]}` : ` ${curve(sg.from[0], sg.from[1], sg.to[0], sg.to[1])}`;
  return { d, mid };
}
