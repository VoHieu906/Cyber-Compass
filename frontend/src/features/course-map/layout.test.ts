import { describe, expect, it } from "vitest";
import { overlaps } from "@/lib/graph/shapes/geometry";
import { GOAL, layoutCourseMap, moveFrom, nodeState, placeLessons, relatedTo } from "./layout";
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

  it("makes room for the focused lesson: everything below moves down, nothing covers the panel", () => {
    const overview = layoutCourseMap(MAP, null, PANEL);
    const focus = layoutCourseMap(MAP, "b", PANEL);
    const panel = focus.focusPanel!;
    expect(panel.y).toBeGreaterThan(focus.boxes.get("b")!.y + focus.boxes.get("b")!.nodeHeight);
    expect(focus.boxes.get("b")).toEqual(overview.boxes.get("b"));
    expect(focus.boxes.get("c")!.y).toBe(overview.boxes.get("c")!.y + PANEL.height + 18);
    for (const [id, box] of focus.boxes) expect(overlaps(box, panel), `${id} covers the panel`).toBe(false);
    expect(focus.width).toBeGreaterThanOrEqual(PANEL.width);
    expect(focus.height).toBeGreaterThan(overview.height + PANEL.height);
  });

  it("draws contributions only from the ends of chains when the course is large", () => {
    const big: CourseMapData = {
      ...MAP,
      lessons: "abcdefghij".split("").map((s) => lesson(s)),
      edges: [{ from: "a", to: "b", type: "requires" }],
    };
    expect(layoutCourseMap(big, null, PANEL).contributes.map((c) => c.from)).not.toContain("a");
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
