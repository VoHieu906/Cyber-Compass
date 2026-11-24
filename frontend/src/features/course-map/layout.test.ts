import { describe, expect, it } from "vitest";
import { overlaps } from "@/lib/graph/shapes/geometry";
import { GOAL, NARROW_NODE, NODE, layoutCourseMap, moveFrom, nodeState, placeLessons, relatedTo } from "./layout";
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
    expect(at(focus, "b")).toMatchObject({ x: at(overview, "b").x, y: at(overview, "b").y, ...EXPANDED, nodeHeight: at(overview, "b").nodeHeight });
    // Later columns move right, later rows move down; the column before stays where it was.
    expect(at(focus, "d").x).toBe(at(overview, "d").x + EXPANDED.width - at(overview, "b").width);
    expect(at(focus, "c").y).toBeGreaterThan(at(focus, "b").y + EXPANDED.height);
    expect(at(focus, "e").y).toBeGreaterThan(at(overview, "e").y);
    expect(at(focus, "a")).toEqual(at(overview, "a"));
    expect(at(focus, GOAL).x).toBeGreaterThan(at(overview, GOAL).x);

    const boxes = [...focus.boxes];
    for (let i = 0; i < boxes.length; i++)
      for (let j = i + 1; j < boxes.length; j++) expect(overlaps(boxes[i][1], boxes[j][1]), `${boxes[i][0]} and ${boxes[j][0]}`).toBe(false);
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
      for (let j = i + 1; j < boxes.length; j++) expect(overlaps(boxes[i][1], boxes[j][1]), `${boxes[i][0]} and ${boxes[j][0]}`).toBe(false);
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
