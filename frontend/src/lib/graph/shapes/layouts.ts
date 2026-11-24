import type { Point } from "../layout";
import { PAD, boxAround, boxIn, centre, connect, overlaps, type Box, type PlacedSub } from "./geometry";
import { routeEdges } from "./route";
import type { ShapeId, SubNode } from "./schemas";

/*
 * One pure layout per architecture shape (F3, D59): sub-nodes and sub-edges in, positions out,
 * always inside the given box and never overlapping (layouts.test.ts checks every shape from
 * one node to its maximum). Rendering is the caller's (features/course-map); markers are keys
 * the renderer words, so layouts stay language-free.
 */

export type ShapeInput = {
  nodes: Pick<SubNode, "id" | "label" | "parent" | "x" | "y" | "cell">[];
  edges: { from: string; to: string; label?: string }[];
  rows?: string[];
  cols?: string[];
};

export type ShapeEdge = {
  from: string;
  to: string;
  d: string;
  label?: string;
  labelAt?: Point;
  /** Drawn with an arrowhead. */
  directed: boolean;
  /** Part of the shape itself (order, spokes, parents), not an authored sub-edge. */
  implicit: boolean;
  /** No clean route around the other parts: drawn beneath them. */
  fallback?: boolean;
};

export type Marker = "push" | "pop" | "front" | "back" | "core" | "outer";

export type Decoration =
  | { kind: "ellipse"; cx: number; cy: number; rx: number; ry: number }
  | { kind: "path"; d: string }
  | { kind: "marker"; key: Marker; x: number; y: number; anchor: "start" | "middle" | "end" }
  | { kind: "header"; text: string; x: number; y: number; anchor: "start" | "middle" | "end" };

export type ShapeLayout = { nodes: Map<string, PlacedSub>; edges: ShapeEdge[]; decorations: Decoration[] };

type Layout = (input: ShapeInput, box: Box) => ShapeLayout;

const GAP = 6;
const MARKER = 20;

/** Authored sub-edges, routed around the other parts with their ends spread along each side (route.ts). */
function authored(input: ShapeInput, nodes: Map<string, PlacedSub>, box: Box): ShapeEdge[] {
  const edges = input.edges.filter((e) => nodes.has(e.from) && nodes.has(e.to) && e.from !== e.to);
  return routeEdges(nodes, edges, box).map((r, i) => ({
    from: r.from,
    to: r.to,
    d: r.d,
    label: edges[i].label,
    labelAt: edges[i].label ? r.mid : undefined,
    directed: true,
    implicit: false,
    fallback: r.fallback,
  }));
}

function chain(ids: string[], nodes: Map<string, PlacedSub>, closed = false): ShapeEdge[] {
  const pairs = ids.slice(0, closed ? ids.length : -1).map((id, i) => [id, ids[(i + 1) % ids.length]] as const);
  return pairs.map(([from, to]) => ({
    from,
    to,
    d: connect(nodes.get(from)!, nodes.get(to)!).d,
    directed: true,
    implicit: true,
  }));
}

/** Ordered bands, first on top, full width. */
const layers: Layout = (input, box) => {
  const n = input.nodes.length;
  const h = Math.min(46, (box.height - 2 * PAD - (n - 1) * GAP) / n);
  const top = (box.height - (n * h + (n - 1) * GAP)) / 2;
  const width = box.width - 2 * PAD - 36;
  const nodes = new Map(
    input.nodes.map((node, i) => [
      node.id,
      boxIn(node.label, { x: PAD, y: top + i * (h + GAP), width, height: h }, h >= 40 ? 2 : 1),
    ]),
  );
  return { nodes, edges: authored(input, nodes, box), decorations: [] };
};

/** LIFO: first at the bottom, last on top, pushed and popped at the open top. */
const stack: Layout = (input, box) => {
  const n = input.nodes.length;
  const width = Math.min(280, box.width * 0.5);
  const x = (box.width - width) / 2;
  const avail = box.height - 2 * PAD - MARKER;
  const h = Math.min(40, (avail - (n - 1) * GAP) / n);
  const bottom = PAD + MARKER + avail;
  const nodes = new Map(
    input.nodes.map((node, i) => [
      node.id,
      boxIn(node.label, { x, y: bottom - (i + 1) * h - i * GAP, width, height: h }, h >= 40 ? 2 : 1),
    ]),
  );
  const topY = bottom - n * h - (n - 1) * GAP;
  return {
    nodes,
    edges: authored(input, nodes, box),
    decorations: [
      {
        kind: "path",
        d: `M ${x - 6} ${topY - 4} L ${x - 6} ${bottom + 4} L ${x + width + 6} ${bottom + 4} L ${x + width + 6} ${topY - 4}`,
      },
      { kind: "marker", key: "push", x: x - 14, y: topY + h / 2, anchor: "end" },
      { kind: "marker", key: "pop", x: x + width + 14, y: topY + h / 2, anchor: "start" },
    ],
  };
};

/**
 * Items in reading order on one row, or two rows when there are more than five. `snake` runs the
 * second row right to left, so a track can bend round from the end of the first.
 */
function lanes(input: ShapeInput, box: Box, reserveTop: number, inset = 0, snake = false): Map<string, PlacedSub> {
  const n = input.nodes.length;
  const rows = n > 5 ? 2 : 1;
  const perRow = Math.ceil(n / rows);
  const width = (box.width - 2 * (PAD + inset) - (perRow - 1) * 18) / perRow;
  const rowH = 58;
  const rowGap = 34;
  const top = reserveTop + (box.height - reserveTop - (rows * rowH + (rows - 1) * rowGap)) / 2;
  return new Map(
    input.nodes.map((node, i) => {
      const row = Math.floor(i / perRow);
      const col = snake && row % 2 ? perRow - 1 - (i % perRow) : i % perRow;
      return [
        node.id,
        boxIn(
          node.label,
          { x: PAD + inset + col * (width + 18), y: top + row * (rowH + rowGap), width, height: rowH },
          3,
        ),
      ];
    }),
  );
}

const SLOT = 6; // the frame's clearance around a slot
const END = 12; // how far an open end runs past the end slot
const BEND = 14; // corner radius of the bend round to the second row

/** A small vee on the frame at (x, y), pointing left (-1) or right (1): the way items move. */
const chevron = (x: number, y: number, dir: number): Decoration => ({
  kind: "path",
  d: `M ${x - dir * 3} ${y - 5} L ${x + dir * 3} ${y} L ${x - dir * 3} ${y + 5}`,
});

/** Midpoints of the gaps between neighbouring slots. */
const gaps = (row: PlacedSub[]) => {
  const s = [...row].sort((a, b) => a.x - b.x);
  return s.slice(1).map((r, i) => (s[i].x + s[i].width + r.x) / 2);
};

/**
 * The queue's frame, drawn in the shape-frame style like the stack's bracket (never a relation
 * line): the slots sit in a tube open at both ends, with vees on its rails pointing the way items
 * move, towards the front. A second row bends round from the end of the first.
 */
function queueFrame([r0, r1]: PlacedSub[][]): Decoration[] {
  const t0 = r0[0].y - SLOT;
  const b0 = r0[0].y + r0[0].height + SLOT;
  const l0 = r0[0].x - END;
  const right = Math.max(...r0.map((r) => r.x + r.width));
  const out: Decoration[] = [];
  const vees = (row: PlacedSub[], ys: number[], dir: number) =>
    gaps(row).forEach((x) => ys.forEach((y) => out.push(chevron(x, y, dir))));
  if (!r1) {
    out.push(
      { kind: "path", d: `M ${l0} ${t0} H ${right + END}` },
      { kind: "path", d: `M ${l0} ${b0} H ${right + END}` },
    );
  } else {
    const t1 = r1[0].y - SLOT;
    const b1 = r1[0].y + r1[0].height + SLOT;
    const l1 = Math.min(...r1.map((r) => r.x)) - END;
    const inner = (t1 - b0) / 2;
    const wall = right + inner + 12;
    out.push(
      {
        kind: "path",
        d: `M ${l0} ${t0} H ${wall - BEND} A ${BEND} ${BEND} 0 0 1 ${wall} ${t0 + BEND} V ${b1 - BEND} A ${BEND} ${BEND} 0 0 1 ${wall - BEND} ${b1} H ${l1}`,
      },
      { kind: "path", d: `M ${l0} ${b0} H ${right} A ${inner} ${inner} 0 0 1 ${right} ${t1} H ${l1}` },
    );
    vees(r1, [t1, b1], 1);
  }
  vees(r0, [t0, b0], -1);
  return out;
}

/** FIFO: the front (dequeued first) at the left of the first row, the back (enqueued last) at the end of the track. */
const queue: Layout = (input, box) => {
  const n = input.nodes.length;
  // Room for the open ends, and for the bend when there is a second row.
  const nodes = lanes(input, box, MARKER, n > 5 ? 20 : 6, true);
  const perRow = n > 5 ? Math.ceil(n / 2) : n;
  const placed = input.nodes.map((node) => nodes.get(node.id)!);
  const rows = [placed.slice(0, perRow), placed.slice(perRow)].filter((r) => r.length);
  const first = placed[0];
  const t0 = first.y - SLOT;
  const back = rows[1]
    ? {
        x: Math.min(...rows[1].map((r) => r.x)) - END,
        y: rows[1][0].y + rows[1][0].height + SLOT + 12,
        anchor: "start" as const,
      }
    : { x: Math.max(...placed.map((r) => r.x + r.width)) + END, y: t0 - 8, anchor: "end" as const };
  return {
    nodes,
    edges: authored(input, nodes, box),
    decorations: [
      ...queueFrame(rows),
      { kind: "marker", key: "front", x: first.x - END, y: t0 - 8, anchor: "start" },
      { kind: "marker", key: "back", ...back },
    ],
  };
};

/** Stages in order, each feeding the next. */
const pipeline: Layout = (input, box) => {
  const nodes = lanes(input, box, 0);
  return {
    nodes,
    edges: [
      ...chain(
        input.nodes.map((n) => n.id),
        nodes,
      ),
      ...authored(input, nodes, box),
    ],
    decorations: [],
  };
};

/** Points on an ellipse, starting at the top and going clockwise. */
function around(n: number, cx: number, cy: number, rx: number, ry: number): Point[] {
  return Array.from({ length: n }, (_, i) => {
    const a = -Math.PI / 2 + (i * 2 * Math.PI) / n;
    return { x: cx + rx * Math.cos(a), y: cy + ry * Math.sin(a) };
  });
}

function ringWidth(n: number): number {
  return n <= 4 ? 150 : n <= 6 ? 130 : n <= 9 ? 112 : 96;
}

/** A closed loop: the last node leads back to the first. */
const cycle: Layout = (input, box) => {
  const n = input.nodes.length;
  const w = ringWidth(n);
  const h = 44;
  const points = around(n, box.width / 2, box.height / 2, box.width / 2 - PAD - w / 2, box.height / 2 - PAD - h / 2);
  const nodes = new Map(input.nodes.map((node, i) => [node.id, boxAround(node.label, points[i].x, points[i].y, w, h)]));
  return {
    nodes,
    edges: [
      ...chain(
        input.nodes.map((x) => x.id),
        nodes,
        n > 2,
      ),
      ...authored(input, nodes, box),
    ],
    decorations: [],
  };
};

/** A centre (the first node) with spokes to the others. */
const hub: Layout = (input, box) => {
  const [centreNode, ...spokes] = input.nodes;
  const w = ringWidth(spokes.length);
  const h = 40;
  const cx = box.width / 2;
  const cy = box.height / 2;
  const points = around(spokes.length, cx, cy, box.width / 2 - PAD - w / 2, box.height / 2 - PAD - h / 2);
  const nodes = new Map<string, PlacedSub>([
    [centreNode.id, boxAround(centreNode.label, cx, cy, Math.min(150, w + 20), 44)],
  ]);
  spokes.forEach((node, i) => nodes.set(node.id, boxAround(node.label, points[i].x, points[i].y, w, h)));
  const hubBox = nodes.get(centreNode.id)!;
  const edges: ShapeEdge[] = spokes.map((s) => ({
    from: centreNode.id,
    to: s.id,
    d: connect(hubBox, nodes.get(s.id)!).d,
    directed: false,
    implicit: true,
  }));
  return { nodes, edges: [...edges, ...authored(input, nodes, box)], decorations: [] };
};

/** Concentric rings: the first node is the core, the last the outermost ring; labels sit on each ring's top. */
const onion: Layout = (input, box) => {
  const n = input.nodes.length;
  const cx = box.width / 2;
  const cy = box.height / 2 + 10;
  const ryMax = box.height / 2 - PAD - 10;
  const rxMax = Math.min(box.width / 2 - PAD, ryMax * 1.7);
  const coreRy = 26;
  const coreRx = 52;
  const step = n > 1 ? (ryMax - coreRy) / (n - 1) : 0;
  const ratio = n > 1 ? (rxMax - coreRx) / (ryMax - coreRy) : 0;
  const pill = Math.min(24, step - 2);
  const nodes = new Map<string, PlacedSub>();
  const decorations: Decoration[] = [];
  input.nodes.forEach((node, i) => {
    const ry = coreRy + i * step;
    const rx = coreRx + i * step * ratio;
    decorations.push({ kind: "ellipse", cx, cy, rx, ry });
    nodes.set(
      node.id,
      i === 0
        ? boxAround(node.label, cx, cy, coreRx * 1.7, 40)
        : boxAround(node.label, cx, cy - ry, Math.min(180, rx), pill),
    );
  });
  decorations.push({ kind: "marker", key: "core", x: cx + coreRx + 6, y: cy + coreRy, anchor: "start" });
  if (n > 1)
    decorations.push({ kind: "marker", key: "outer", x: cx + rxMax * 0.72, y: cy + ryMax * 0.82, anchor: "start" });
  return { nodes, edges: authored(input, nodes, box), decorations };
};

/** A hierarchy: depth by parent, leaves spread evenly in author order, parents over their children. */
const tree: Layout = (input, box) => {
  const children = new Map<string, string[]>();
  let root = input.nodes[0]?.id;
  for (const node of input.nodes) {
    if (node.parent === undefined) root = node.id;
    else children.set(node.parent, [...(children.get(node.parent) ?? []), node.id]);
  }
  const depth = new Map<string, number>();
  const order: string[] = [];
  const visit = (id: string, d: number) => {
    if (depth.has(id)) return;
    depth.set(id, d);
    const kids = children.get(id) ?? [];
    if (!kids.length) order.push(id);
    kids.forEach((k) => visit(k, d + 1));
  };
  visit(root, 0);
  input.nodes.forEach((node) => visit(node.id, 0)); // anything unreachable (invalid data) still gets a place
  const levels = Math.max(...depth.values()) + 1;
  const slot = (box.width - 2 * PAD) / Math.max(1, order.length);
  const levelH = (box.height - 2 * PAD) / levels;
  const h = Math.min(40, levelH - 10);
  const w = Math.min(150, slot - 8);
  const xOf = new Map<string, number>();
  const place = (id: string): number => {
    if (xOf.has(id)) return xOf.get(id)!;
    xOf.set(id, PAD + slot / 2); // a parent loop (invalid data) ends here
    const xs = (children.get(id) ?? []).map(place);
    const x = xs.length ? (Math.min(...xs) + Math.max(...xs)) / 2 : PAD + slot * (order.indexOf(id) + 0.5);
    xOf.set(id, x);
    return x;
  };
  place(root);
  const nodes = new Map(
    input.nodes.map((node) => {
      const x = xOf.get(node.id) ?? PAD + slot / 2;
      const y = PAD + depth.get(node.id)! * levelH + levelH / 2;
      return [node.id, boxIn(node.label, { x: x - w / 2, y: y - h / 2, width: w, height: h }, h >= 40 ? 2 : 1)];
    }),
  );
  const edges: ShapeEdge[] = input.nodes
    .filter((n) => n.parent !== undefined && nodes.has(n.parent))
    .map((n) => {
      const p = nodes.get(n.parent!)!;
      const c = nodes.get(n.id)!;
      const midY = (p.y + p.height + c.y) / 2;
      return {
        from: n.parent!,
        to: n.id,
        d: `M ${centre(p).x} ${p.y + p.height} L ${centre(p).x} ${midY} L ${centre(c).x} ${midY} L ${centre(c).x} ${c.y}`,
        directed: false,
        implicit: true,
      };
    });
  return { nodes, edges: [...edges, ...authored(input, nodes, box)], decorations: [] };
};

/** A free network at the author's positions (0 to 100 on each axis), nudged apart where boxes would touch. */
const mesh: Layout = (input, box) => {
  const w = 118;
  const h = 40;
  const rects = input.nodes.map((node) => {
    const cx = PAD + w / 2 + ((node.x ?? 50) / 100) * (box.width - 2 * PAD - w);
    const cy = PAD + h / 2 + ((node.y ?? 50) / 100) * (box.height - 2 * PAD - h);
    return boxAround(node.label, cx, cy, w, h);
  });
  // shortcut: a few relaxation passes separate close neighbours; a crowded author layout can still touch. Upgrade to a real overlap remover if authors hit it.
  for (let pass = 0; pass < 40; pass++) {
    let moved = false;
    for (let i = 0; i < rects.length; i++) {
      for (let j = i + 1; j < rects.length; j++) {
        const a = rects[i];
        const b = rects[j];
        if (!overlaps(a, b, 6)) continue;
        moved = true;
        const dy = centre(b).y - centre(a).y || 1;
        const shift = (Math.min(a.y + a.height, b.y + b.height) - Math.max(a.y, b.y) + 8) / 2;
        a.y -= Math.sign(dy) * shift;
        b.y += Math.sign(dy) * shift;
      }
    }
    for (const r of rects) r.y = Math.min(box.height - PAD - r.height, Math.max(PAD, r.y));
    if (!moved) break;
  }
  const nodes = new Map(input.nodes.map((node, i) => [node.id, rects[i]]));
  return { nodes, edges: authored(input, nodes, box), decorations: [] };
};

/** A small grid with column headers on top and row headers on the left; each node fills its cell. */
const matrix: Layout = (input, box) => {
  const rows = input.rows ?? [""];
  const cols = input.cols ?? ["", ""];
  const head = 24;
  const side = rows.length > 1 || rows[0] ? 96 : 0;
  const cw = (box.width - 2 * PAD - side - (cols.length - 1) * GAP) / cols.length;
  const ch = Math.min(80, (box.height - 2 * PAD - head - (rows.length - 1) * GAP) / rows.length);
  const decorations: Decoration[] = [
    ...cols.map((text, c): Decoration => ({
      kind: "header",
      text,
      x: PAD + side + c * (cw + GAP) + cw / 2,
      y: PAD + head / 2,
      anchor: "middle",
    })),
    ...(side
      ? rows.map((text, r): Decoration => ({
          kind: "header",
          text,
          x: PAD,
          y: PAD + head + r * (ch + GAP) + ch / 2,
          anchor: "start",
        }))
      : []),
  ];
  const nodes = new Map(
    input.nodes.map((node) => {
      const [r, c] = node.cell ?? [0, 0];
      return [
        node.id,
        boxIn(node.label, { x: PAD + side + c * (cw + GAP), y: PAD + head + r * (ch + GAP), width: cw, height: ch }, 3),
      ];
    }),
  );
  return { nodes, edges: authored(input, nodes, box), decorations };
};

export const LAYOUTS: Record<ShapeId, Layout> = {
  layers,
  onion,
  stack,
  queue,
  pipeline,
  cycle,
  hub,
  tree,
  mesh,
  matrix,
};
