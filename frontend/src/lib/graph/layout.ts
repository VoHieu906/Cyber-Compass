import { Graph, layout } from "@dagrejs/dagre";

/*
 * Automatic layered layout for node-and-edge diagrams (the flow and tree blocks; the Phase 5
 * roadmap graph can reuse it). Pure: takes sizes, returns positions, so it is unit-tested and
 * renderer-agnostic. Lesson diagrams draw the result as static SVG (lib/graph/graph-view.tsx).
 */

export type LayoutNode = { id: string; width: number; height: number };
export type LayoutEdge = { from: string; to: string; label?: string };
export type Point = { x: number; y: number };

export type PlacedNode = LayoutNode & { x: number; y: number };
export type PlacedEdge = LayoutEdge & { points: Point[]; labelAt: Point | null };
export type GraphLayout = { nodes: Map<string, PlacedNode>; edges: PlacedEdge[]; width: number; height: number };

export const EDGE_LABEL = { width: 72, height: 18 };

export function layoutGraph(
  nodes: LayoutNode[],
  edges: LayoutEdge[],
  {
    direction = "TB",
    nodeGap = 28,
    rankGap = 44,
    margin = 12,
    order = [],
  }: Partial<{
    direction: "TB" | "LR";
    nodeGap: number;
    rankGap: number;
    margin: number;
    /** Pairs [left, right] of nodes in the same rank that must keep this order (tree siblings). */
    order: [string, string][];
  }> = {},
): GraphLayout {
  const g = new Graph({ multigraph: true });
  g.setGraph({ rankdir: direction, nodesep: nodeGap, ranksep: rankGap, marginx: margin, marginy: margin, edgesep: 16 });
  g.setDefaultEdgeLabel(() => ({}));

  for (const n of nodes) g.setNode(n.id, { width: n.width, height: n.height });
  edges.forEach((e, i) => {
    const label = e.label ? { ...EDGE_LABEL, labelpos: "c" as const } : {};
    g.setEdge(e.from, e.to, label, String(i));
  });

  layout(g, order.length ? { constraints: order.map(([left, right]) => ({ left, right })) } : undefined);

  const placed = new Map<string, PlacedNode>();
  for (const n of nodes) {
    const at = g.node(n.id);
    placed.set(n.id, { ...n, x: at.x, y: at.y });
  }

  const placedEdges = edges.map((e, i): PlacedEdge => {
    const at = g.edge({ v: e.from, w: e.to, name: String(i) });
    const points = (at?.points ?? []).map((p: Point) => ({ x: p.x, y: p.y }));
    const labelAt = e.label && at && typeof at.x === "number" && typeof at.y === "number" ? { x: at.x, y: at.y } : null;
    return { ...e, points, labelAt };
  });

  const graph = g.graph();
  return { nodes: placed, edges: placedEdges, width: graph.width ?? 0, height: graph.height ?? 0 };
}

/** A smooth SVG path through dagre's edge points (straight segments when there are only two). */
export function edgePath(points: Point[]): string {
  if (points.length === 0) return "";
  if (points.length < 3) return points.map((p, i) => `${i ? "L" : "M"} ${p.x} ${p.y}`).join(" ");
  let d = `M ${points[0].x} ${points[0].y}`;
  for (let i = 1; i < points.length - 1; i++) {
    const mid = { x: (points[i].x + points[i + 1].x) / 2, y: (points[i].y + points[i + 1].y) / 2 };
    d += ` Q ${points[i].x} ${points[i].y} ${i === points.length - 2 ? `${points[i + 1].x} ${points[i + 1].y}` : `${mid.x} ${mid.y}`}`;
  }
  return d;
}

/** Rough text width for laying out labels before they are drawn (no DOM measurement). */
export function textWidth(text: string, fontSize = 13): number {
  return Math.ceil(text.length * fontSize * 0.56);
}

/** Splits a label into at most `maxLines` lines of roughly `maxChars`, on word boundaries. */
export function wrapLabel(text: string, maxChars = 22, maxLines = 3): string[] {
  const lines: string[] = [];
  let line = "";
  for (const word of text.split(/\s+/).filter(Boolean)) {
    if (line && (line + " " + word).length > maxChars) {
      lines.push(line);
      line = word;
    } else {
      line = line ? `${line} ${word}` : word;
    }
  }
  if (line) lines.push(line);
  if (lines.length > maxLines) {
    const kept = lines.slice(0, maxLines);
    kept[maxLines - 1] = kept[maxLines - 1].replace(/.{0,1}$/, "…");
    return kept;
  }
  return lines;
}
