import { describe, expect, it } from "vitest";
import { gridEdgePath, itemHeight, layoutGrid, type GridMetrics } from "./grid";

const m: GridMetrics = { left: 10, top: 20, colWidth: 100, colGap: 40, rowGap: 30, itemGap: 8, nodeHeight: 50, childHeight: 20, childGap: 6 };

describe("layoutGrid", () => {
  it("places columns side by side and stacks items in a cell in order", () => {
    const g = layoutGrid(
      [
        { id: "a", row: 0, col: 0, children: 0 },
        { id: "b", row: 0, col: 0, children: 0 },
        { id: "c", row: 0, col: 1, children: 0 },
      ],
      1,
      2,
      m,
    );
    expect(g.items.get("a")).toMatchObject({ x: 10, y: 20, width: 100, height: 50 });
    expect(g.items.get("b")).toMatchObject({ x: 10, y: 78 });
    expect(g.items.get("c")).toMatchObject({ x: 150, y: 20 });
    expect(g.width).toBe(10 + 100 * 2 + 40);
    expect(g.height).toBe(20 + 50 + 8 + 50);
  });

  it("grows an item by its child rows and pushes later items and rows down", () => {
    const g = layoutGrid(
      [
        { id: "a", row: 0, col: 0, children: 3 },
        { id: "b", row: 0, col: 0, children: 0 },
        { id: "c", row: 1, col: 1, children: 0 },
      ],
      2,
      2,
      m,
    );
    expect(itemHeight({ children: 3 }, m)).toBe(50 + 6 + 60);
    expect(g.items.get("b")!.y).toBe(20 + 116 + 8);
    expect(g.rows[1].y).toBe(20 + 116 + 8 + 50 + 30);
    expect(g.items.get("c")!.y).toBe(g.rows[1].y);
  });

  it("keeps empty rows at the minimum height", () => {
    const g = layoutGrid([{ id: "a", row: 1, col: 0, children: 0 }], 2, 1, { ...m, minRowHeight: 40 });
    expect(g.rows[0]).toEqual({ y: 20, height: 40 });
    expect(g.items.get("a")!.y).toBe(20 + 40 + 30);
  });

  it("is deterministic for the same input", () => {
    const items = [
      { id: "a", row: 0, col: 0, children: 2 },
      { id: "b", row: 0, col: 1, children: 0 },
    ];
    expect(layoutGrid(items, 1, 2, m)).toEqual(layoutGrid(items, 1, 2, m));
  });
});

describe("gridEdgePath", () => {
  const box = (x: number, y: number) => ({ x, y, width: 100, height: 50, nodeHeight: 50 });

  it("runs forward edges from the right side to the left side, at node mid-height", () => {
    expect(gridEdgePath(box(0, 0), box(140, 100))).toBe("M 100 25 C 120 25, 120 125, 140 125");
  });

  it("runs backward edges from the left side to the right side", () => {
    expect(gridEdgePath(box(140, 0), box(0, 0))).toBe("M 140 25 C 120 25, 120 25, 100 25");
  });

  it("bows same-column edges out to the left", () => {
    expect(gridEdgePath(box(10, 0), box(10, 100))).toBe("M 10 25 C -18 25, -18 125, 10 125");
  });

  it("anchors on the node, not on its expanded children", () => {
    const expanded = { ...box(0, 0), height: 200 };
    expect(gridEdgePath(expanded, box(140, 0)).startsWith("M 100 25")).toBe(true);
  });

  it("routes an edge that skips a column through a free gap, never behind a node", () => {
    const g = layoutGrid(
      [
        { id: "src", row: 0, col: 0, children: 0 },
        { id: "mid1", row: 0, col: 1, children: 0 },
        { id: "mid2", row: 0, col: 1, children: 0 },
        { id: "dst", row: 0, col: 2, children: 0 },
      ],
      1,
      3,
      m,
    );
    const d = gridEdgePath(g.items.get("src")!, g.items.get("dst")!, g);
    // The middle column holds items at y 20 to 70 and 78 to 128: free channels at 12, 74 and 136; 74 is nearest.
    const straight = d.match(/L (\S+) (\S+)/)!;
    expect(Number(straight[2])).toBe(74);
    expect(d.startsWith("M 110 45")).toBe(true);
    expect(d.endsWith("290 45")).toBe(true);
  });

  it("walks backward skip edges from the source to the target", () => {
    const g = layoutGrid(
      [
        { id: "dst", row: 0, col: 0, children: 0 },
        { id: "mid", row: 0, col: 1, children: 0 },
        { id: "src", row: 0, col: 2, children: 0 },
      ],
      1,
      3,
      m,
    );
    const d = gridEdgePath(g.items.get("src")!, g.items.get("dst")!, g);
    expect(d.startsWith("M 290 45")).toBe(true);
    expect(d.endsWith("110 45")).toBe(true);
  });
});
