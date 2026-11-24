import { describe, expect, it } from "vitest";
import { overlaps, type Rect } from "@/lib/graph/shapes/geometry";
import { layoutShape } from "@/lib/graph/shapes/registry";
import {
  EXPANDED as FRAME,
  GOAL,
  NARROW_NODE,
  NODE,
  expandedSize,
  layoutCourseMap,
  moveFrom,
  nodeState,
  placeLessons,
  relatedTo,
  detailHeight,
  routePartLinks,
} from "./layout";
import type { CourseMapData, MapLesson } from "./types";

const lesson = (slug: string, extra: Partial<MapLesson> = {}): MapLesson => ({
  slug,
  title: slug,
  placement: null,
  problem: null,
  shape: null,
  rows: null,
  cols: null,
  nodes: [],
  edges: [],
  ...extra,
});

const MAP: CourseMapData = {
  problem: "Protect data",
  layout: "auto",
  lanes: [],
  lessons: ["a", "b", "c", "d", "e"].map((s) => lesson(s)),
  edges: [
    { from: "a", to: "b", type: "requires" },
    { from: "a", to: "c", type: "builds-on" },
    { from: "b", to: "d", type: "feeds-into" },
    { from: "c", to: "d", type: "parallel-to" },
    { from: "b", to: "c", type: "contrasts-with" },
  ],
};
const PANEL = { width: 624, height: 480 };
const EXPANDED = PANEL;

describe("placeLessons", () => {
  it("places lessons automatically in columns by relation depth, in course order", () => {
    const { cells } = placeLessons(MAP);
    expect(Object.fromEntries(cells)).toEqual({
      a: { col: 0, row: 0 },
      b: { col: 1, row: 0 },
      c: { col: 1, row: 1 },
      d: { col: 2, row: 0 },
      e: { col: 0, row: 1 },
    });
  });

  it("survives a loop in the relations", () => {
    const loop = {
      ...MAP,
      edges: [
        { from: "a", to: "b", type: "requires" as const },
        { from: "b", to: "a", type: "requires" as const },
      ],
    };
    expect(Math.max(...[...placeLessons(loop).cells.values()].map((c) => c.col))).toBeLessThan(5);
  });

  it("uses the author's cells, stacks lanes as bands and puts unplaced lessons after them", () => {
    const manual: CourseMapData = {
      ...MAP,
      layout: "manual",
      lanes: [
        { id: "people", label: "People" },
        { id: "tech", label: "Technology" },
      ],
      lessons: [
        lesson("a", { placement: { col: 0, row: 0, lane: "tech" } }),
        lesson("b", { placement: { col: 1, row: 1, lane: "people" } }),
        lesson("c", { placement: { col: 2, row: 0 } }),
        lesson("new"),
      ],
    };
    const { cells, bands } = placeLessons(manual);
    expect(Object.fromEntries(cells)).toEqual({
      b: { col: 1, row: 1 },
      a: { col: 0, row: 2 },
      c: { col: 2, row: 3 },
      new: { col: 0, row: 4 },
    });
    expect(bands).toEqual([
      { lane: "people", label: "People", row: 0, rows: 2 },
      { lane: "tech", label: "Technology", row: 2, rows: 1 },
    ]);
  });
});

describe("layoutCourseMap", () => {
  it("never overlaps nodes, and puts the goal after the last column", () => {
    const layout = layoutCourseMap(MAP, null, PANEL);
    const boxes = [...layout.boxes.values()];
    for (let i = 0; i < boxes.length; i++)
      for (let j = i + 1; j < boxes.length; j++) expect(overlaps(boxes[i], boxes[j])).toBe(false);
    expect(layout.boxes.get(GOAL)!.x).toBeGreaterThan(
      Math.max(...["a", "b", "c", "d", "e"].map((s) => layout.boxes.get(s)!.x)),
    );
    expect(layout.edges).toHaveLength(5);
    expect(layout.contributes.map((c) => c.from)).toEqual(["a", "b", "c", "d", "e"]);
  });

  it("grows the focused node in place and reflows the rest around it, right and down, without overlaps", () => {
    const overview = layoutCourseMap(MAP, null, EXPANDED);
    const focus = layoutCourseMap(MAP, "b", EXPANDED);
    const at = (l: typeof overview, id: string) => l.boxes.get(id)!;

    // b keeps its corner and grows; its title card (nodeHeight) keeps the node's height.
    expect(at(focus, "b")).toMatchObject({
      x: at(overview, "b").x,
      y: at(overview, "b").y,
      ...EXPANDED,
      nodeHeight: at(overview, "b").nodeHeight,
    });
    // Later columns move right, later rows move down; the column before stays where it was.
    expect(at(focus, "d").x).toBe(at(overview, "d").x + EXPANDED.width - at(overview, "b").width);
    expect(at(focus, "c").y).toBeGreaterThan(at(focus, "b").y + EXPANDED.height);
    expect(at(focus, "e").y).toBeGreaterThan(at(overview, "e").y);
    expect(at(focus, "a")).toEqual(at(overview, "a"));
    expect(at(focus, GOAL).x).toBeGreaterThan(at(overview, GOAL).x);

    const boxes = [...focus.boxes];
    for (let i = 0; i < boxes.length; i++)
      for (let j = i + 1; j < boxes.length; j++)
        expect(overlaps(boxes[i][1], boxes[j][1]), `${boxes[i][0]} and ${boxes[j][0]}`).toBe(false);
    expect(focus.width).toBeGreaterThan(overview.width);
  });

  it("draws contributions only from the ends of chains when the course is large", () => {
    const big: CourseMapData = {
      ...MAP,
      lessons: "abcdefghij".split("").map((s) => lesson(s)),
      edges: [{ from: "a", to: "b", type: "requires" }],
    };
    expect(layoutCourseMap(big, null, PANEL).contributes.map((c) => c.from)).not.toContain("a");
  });

  it("narrow (D61): smaller nodes, the goal as a band under every lesson, lines running down into it", () => {
    const wide = layoutCourseMap(MAP, null, NODE);
    const narrow = layoutCourseMap(MAP, null, NARROW_NODE, true);
    const goal = narrow.boxes.get(GOAL)!;
    const lessons = ["a", "b", "c", "d", "e"].map((s) => narrow.boxes.get(s)!);

    expect(narrow.node).toEqual(NARROW_NODE);
    expect(lessons.every((b) => b.width === NARROW_NODE.width)).toBe(true);
    expect(narrow.width).toBeLessThan(wide.width);
    // Under the lowest lesson, spanning from the first column to the last.
    expect(goal.y).toBeGreaterThan(Math.max(...lessons.map((b) => b.y + b.height)));
    expect(goal.x).toBe(Math.min(...lessons.map((b) => b.x)));
    expect(goal.x + goal.width).toBe(Math.max(...lessons.map((b) => b.x + b.width)));
    expect(narrow.height).toBe(goal.y + goal.height);
    // Every contribution ends on the band's top edge.
    for (const c of narrow.contributes) expect(c.d.endsWith(` ${goal.y}`)).toBe(true);

    // Focus still grows in place without overlaps, and the band follows the reflow.
    const focus = layoutCourseMap(MAP, "b", EXPANDED, true);
    const boxes = [...focus.boxes];
    for (let i = 0; i < boxes.length; i++)
      for (let j = i + 1; j < boxes.length; j++)
        expect(overlaps(boxes[i][1], boxes[j][1]), `${boxes[i][0]} and ${boxes[j][0]}`).toBe(false);
    expect(focus.boxes.get(GOAL)!.width).toBeGreaterThan(goal.width);
  });
});

describe("focus state and keys", () => {
  it("relates lessons through edges either way and through sub-node links", () => {
    const linked = {
      ...MAP,
      lessons: MAP.lessons.map((l) => (l.slug === "e" ? { ...l, nodes: [{ id: "x", label: "X", lesson: "a" }] } : l)),
    };
    expect([...relatedTo(linked, "c")].sort()).toEqual(["a", "b", "d"]);
    expect([...relatedTo(linked, "e")]).toEqual(["a"]);
    expect(nodeState(linked, "c", "c")).toBe("focused");
    expect(nodeState(linked, "c", "a")).toBe("related");
    expect(nodeState(linked, "c", "e")).toBe("dimmed");
    expect(nodeState(linked, null, "e")).toBe("normal");
  });

  it("moves along relations with ← and →, in course order with ↑ and ↓, and reaches the goal", () => {
    expect(moveFrom(MAP, "a", "ArrowRight")).toBe("b");
    expect(moveFrom(MAP, "d", "ArrowLeft")).toBe("c");
    expect(moveFrom(MAP, "d", "ArrowRight")).toBe("e");
    expect(moveFrom(MAP, "e", "ArrowRight")).toBe(GOAL);
    expect(moveFrom(MAP, "c", "ArrowDown")).toBe("d");
    expect(moveFrom(MAP, "a", "ArrowUp")).toBe("a");
    expect(moveFrom(MAP, GOAL, "ArrowLeft")).toBe("e");
  });
});

describe("part links in focus mode", () => {
  // The OSI model: seven layers, five of them leading to other lessons around the expanded node.
  const box = { width: 600, height: 340 };
  const panel = expandedSize(box);
  const area = { x: (panel.width - box.width) / 2, y: FRAME.header + FRAME.gap };
  const shape = layoutShape(
    "layers",
    { nodes: [7, 6, 5, 4, 3, 2, 1].map((n) => ({ id: `l${n}`, label: `${n} Layer` })), edges: [] },
    box,
  );
  const parts = new Map<string, Rect>(
    [...shape.nodes].map(([id, r]) => [id, { ...r, x: r.x + area.x, y: r.y + area.y }]),
  );
  const at = (x: number, y: number): Rect => ({ x, y, width: NODE.width, height: NODE.height });
  const links = [
    { id: "l1", key: "ethernet", to: at(-300, panel.height + 80) },
    { id: "l2", key: "switching", to: at(40, panel.height + 80) },
    { id: "l3", key: "ip", to: at(panel.width + 72, 200) },
    { id: "l4", key: "tcp", to: at(panel.width + 72, panel.height + 80) },
    { id: "l7", key: "apps", to: at(panel.width + 72, -200) },
  ];

  it("leaves every part without crossing another part, the title card or the detail strip", () => {
    const routes = routePartLinks(panel, NODE, parts, links);
    expect(routes.filter((r) => r.fallback).map((r) => r.id)).toEqual([]);
    const card = { x: 0, y: 0, ...NODE };
    const strip = {
      x: FRAME.pad,
      y: panel.height - FRAME.detail + 4,
      width: panel.width - 24,
      height: FRAME.detail - 16,
    };
    for (const r of routes) {
      const n = r.inner.match(/-?\d+(\.\d+)?/g)!.map(Number);
      const pts = Array.from({ length: n.length / 2 }, (_, i) => ({ x: n[2 * i], y: n[2 * i + 1] }));
      for (let i = 1; i < pts.length; i++) {
        const seg = {
          x: Math.min(pts[i - 1].x, pts[i].x),
          y: Math.min(pts[i - 1].y, pts[i].y),
          width: Math.abs(pts[i].x - pts[i - 1].x) || 0.01,
          height: Math.abs(pts[i].y - pts[i - 1].y) || 0.01,
        };
        for (const [id, rect] of [...parts, ["card", card] as const, ["strip", strip] as const])
          if (id !== r.id) expect(overlaps(seg, rect), `${r.id} crosses ${id}`).toBe(false);
      }
    }
  });

  it("routes them within a frame budget", () => {
    routePartLinks(panel, NODE, parts, links);
    const t0 = performance.now();
    for (let i = 0; i < 10; i++) routePartLinks(panel, NODE, parts, links);
    expect((performance.now() - t0) / 10).toBeLessThan(16);
  });
});

describe("the focus panel's detail strip", () => {
  it("is as tall as what it shows, so no empty band is left", () => {
    const width = 624;
    expect(detailHeight("Short problem?", true, width)).toBe(FRAME.detail);
    expect(detailHeight(null, false, width)).toBe(FRAME.pad);
    const one = detailHeight("How do packets cross networks?", false, width);
    const two = detailHeight(
      "How does a packet cross networks that nobody controls end to end, and who decides the way?".repeat(2),
      false,
      width,
    );
    expect(one).toBeLessThan(two);
    expect(two).toBeLessThan(FRAME.detail);
  });
});

describe("edge ends on lesson nodes", () => {
  // A busy lesson like IP addressing: two lessons lead into it, five lead out, and it feeds the goal.
  const HUB: CourseMapData = {
    problem: "Get data across",
    layout: "auto",
    lanes: [],
    lessons: ["s1", "s2", "hub", "t1", "t2", "t3", "t4", "t5"].map((s) => lesson(s)),
    edges: [
      { from: "s1", to: "hub", type: "requires" },
      { from: "s2", to: "hub", type: "builds-on" },
      ...["t1", "t2", "t3", "t4", "t5"].map((to, i) => ({
        from: "hub",
        to,
        type: "requires" as const,
        ...(i < 3 ? { label: "addresses" } : {}),
      })),
    ],
  };
  const layout = layoutCourseMap(HUB, null, NODE);
  const ends = (d: string) => {
    const n = d.match(/-?\d+(\.\d+)?/g)!.map(Number);
    return { start: { x: n[0], y: n[1] }, end: { x: n[n.length - 2], y: n[n.length - 1] } };
  };
  const lines = [
    ...layout.edges.map((e) => ({ from: e.from, to: e.to, ...ends(e.d) })),
    ...layout.contributes.map((c) => ({ from: c.from, to: GOAL, ...ends(c.d) })),
  ];

  it("spreads the ends sharing a node side, so no two lines or arrowheads meet at one point", () => {
    const bySide = new Map<string, number[]>();
    for (const l of lines)
      for (const [node, p] of [
        [l.from, l.start],
        [l.to, l.end],
      ] as const) {
        const key = `${node}@${p.x}`;
        bySide.set(key, [...(bySide.get(key) ?? []), p.y]);
      }
    const hubRight = bySide.get(`hub@${layout.boxes.get("hub")!.x + NODE.width}`)!;
    expect(hubRight).toHaveLength(6);
    for (const [side, ys] of bySide) {
      const sorted = [...ys].sort((a, b) => a - b);
      for (let i = 1; i < sorted.length; i++) expect(sorted[i] - sorted[i - 1], side).toBeGreaterThanOrEqual(8);
    }
  });

  it("orders the ends on a side by where the other end sits, so they do not cross at the node", () => {
    const out = lines.filter((l) => l.from === "hub" && l.to !== GOAL).sort((a, b) => a.start.y - b.start.y);
    const targets = out.map((l) => layout.boxes.get(l.to)!.y);
    expect(targets).toEqual([...targets].sort((a, b) => a - b));
  });

  it("keeps relation labels apart", () => {
    const labelled = layout.edges.filter((e) => e.label);
    for (let i = 0; i < labelled.length; i++)
      for (let j = i + 1; j < labelled.length; j++) {
        const [a, b] = [labelled[i].labelAt, labelled[j].labelAt];
        expect(Math.abs(a.x - b.x) > 80 || Math.abs(a.y - b.y) >= 14, `${i} and ${j}`).toBe(true);
      }
  });
});
