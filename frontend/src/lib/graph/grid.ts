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

export function itemHeight(item: Pick<GridItem, "children">, m: GridMetrics): number {
  return m.nodeHeight + (item.children > 0 ? m.childGap + item.children * m.childHeight : 0);
}

export function layoutGrid(items: GridItem[], rowCount: number, colCount: number, m: GridMetrics): GridLayout {
  const cellHeight = new Map<string, number>();
  for (const item of items) {
    const key = `${item.row}:${item.col}`;
    const used = cellHeight.get(key);
    cellHeight.set(key, (used === undefined ? 0 : used + m.itemGap) + itemHeight(item, m));
  }

  const columns = Array.from({ length: colCount }, (_, c) => ({ x: m.left + c * (m.colWidth + m.colGap) }));
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
    placed.set(item.id, { x: columns[item.col].x, y: rows[item.row].y + offset, width: m.colWidth, height, nodeHeight: m.nodeHeight });
    cursor.set(key, offset + height + m.itemGap);
  }

  return {
    items: placed,
    columns,
    rows,
    width: m.left + colCount * m.colWidth + Math.max(0, colCount - 1) * m.colGap,
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
function channels(layout: GridLayout, col: number, colWidth: number): number[] {
  const x = layout.columns[col].x;
  const spans = [...layout.items.values()].filter((b) => b.x === x && b.width === colWidth).map((b) => [b.y, b.y + b.height] as const).sort((a, b) => a[0] - b[0]);
  if (!spans.length) return [];
  const free = [spans[0][0] - 8];
  for (let i = 1; i < spans.length; i++) if (spans[i][0] > spans[i - 1][1]) free.push((spans[i - 1][1] + spans[i][0]) / 2);
  free.push(spans[spans.length - 1][1] + 8);
  return free;
}

/**
 * An edge between two nodes (the node part of each box, not its children). Forward edges run
 * from the right side to the left side, and through a free channel of every column they skip;
 * backward edges run the other way; edges inside one column bow out into the gutter on the
 * left, so they never cross the nodes between them. Pass the layout to route around nodes.
 */
export function gridEdgePath(from: GridBox, to: GridBox, layout?: GridLayout, bow = 28): string {
  const fy = from.y + from.nodeHeight / 2;
  const ty = to.y + to.nodeHeight / 2;
  const forward = to.x >= from.x + from.width;
  const backward = to.x + to.width <= from.x;
  if (!forward && !backward) return `M ${from.x} ${fy} C ${from.x - bow} ${fy}, ${to.x - bow} ${ty}, ${to.x} ${ty}`;

  const [left, right, ly, ry] = forward ? [from, to, fy, ty] : [to, from, ty, fy];
  // Segments left to right: leave the left node, cross each skipped column straight along a free
  // channel, enter the right node. Curves join them with flat tangents.
  const segments: { from: [number, number]; to: [number, number]; straight: boolean }[] = [];
  let at: [number, number] = [left.x + left.width, ly];
  for (const [i, column] of (layout?.columns ?? []).entries()) {
    if (column.x <= left.x || column.x >= right.x) continue;
    const free = channels(layout!, i, left.width);
    if (!free.length) continue;
    const target = (at[1] + ry) / 2;
    const y = free.reduce((best, ch) => (Math.abs(ch - target) < Math.abs(best - target) ? ch : best));
    segments.push({ from: at, to: [column.x, y], straight: false }, { from: [column.x, y], to: [column.x + left.width, y], straight: true });
    at = [column.x + left.width, y];
  }
  segments.push({ from: at, to: [right.x, ry], straight: false });

  // The path ends at the target, where the arrowhead goes: backward edges are walked right to left.
  const ordered = forward ? segments : segments.reverse().map((sg) => ({ from: sg.to, to: sg.from, straight: sg.straight }));
  let d = `M ${ordered[0].from[0]} ${ordered[0].from[1]}`;
  for (const sg of ordered) d += sg.straight ? ` L ${sg.to[0]} ${sg.to[1]}` : ` ${curve(sg.from[0], sg.from[1], sg.to[0], sg.to[1])}`;
  return d;
}
