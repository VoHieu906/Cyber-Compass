import { describe, expect, it } from "vitest";
import { edgePath, layoutGraph, wrapLabel } from "./layout";

describe("layoutGraph", () => {
  const nodes = ["a", "b", "c"].map((id) => ({ id, width: 80, height: 30 }));

  it("places ranks top to bottom without overlap", () => {
    const g = layoutGraph(nodes, [
      { from: "a", to: "b" },
      { from: "a", to: "c" },
    ]);
    const [a, b, c] = ["a", "b", "c"].map((id) => g.nodes.get(id)!);
    expect(b.y).toBeGreaterThan(a.y);
    expect(b.y).toBe(c.y);
    expect(Math.abs(b.x - c.x)).toBeGreaterThanOrEqual(80);
    expect(g.width).toBeGreaterThan(160);
    expect(g.edges.every((e) => e.points.length >= 2)).toBe(true);
  });

  it("lays out left to right, keeps parallel edges apart and positions labels", () => {
    const g = layoutGraph(
      nodes.slice(0, 2),
      [
        { from: "a", to: "b", label: "yes" },
        { from: "b", to: "a" },
      ],
      { direction: "LR" },
    );
    expect(g.nodes.get("b")!.x).toBeGreaterThan(g.nodes.get("a")!.x);
    expect(g.edges[0].labelAt).not.toBeNull();
    expect(g.edges[1].labelAt).toBeNull();
  });

  it("keeps siblings in the given left-to-right order", () => {
    const kids = ["first", "second", "third"];
    const tree = [{ id: "root", width: 80, height: 30 }, ...kids.map((id) => ({ id, width: 80, height: 30 }))];
    const edges = kids.map((id) => ({ from: "root", to: id }));
    const order: [string, string][] = [
      ["first", "second"],
      ["second", "third"],
    ];
    const g = layoutGraph(tree, edges, { order });
    const xs = kids.map((id) => g.nodes.get(id)!.x);
    expect(xs).toEqual([...xs].sort((a, b) => a - b));
  });
});

describe("helpers", () => {
  it("wraps labels on words and truncates past the line limit", () => {
    expect(wrapLabel("Reset password and revoke sessions", 16)).toEqual(["Reset password", "and revoke", "sessions"]);
    expect(wrapLabel("one two three four five six seven", 6, 2)).toHaveLength(2);
    expect(wrapLabel("one two three four five six seven", 6, 2)[1].endsWith("…")).toBe(true);
  });

  it("draws a straight path for two points and a curve for more", () => {
    expect(
      edgePath([
        { x: 0, y: 0 },
        { x: 10, y: 10 },
      ]),
    ).toBe("M 0 0 L 10 10");
    expect(
      edgePath([
        { x: 0, y: 0 },
        { x: 5, y: 5 },
        { x: 10, y: 0 },
      ]),
    ).toContain("Q");
  });
});
