import type { ShapeId } from "./schemas";

/*
 * A small glyph per shape (20×20, stroke only), shown on overview nodes and in the legend so a
 * stack lesson looks different from a pipeline lesson before any click (F3). Kept apart from the
 * layouts so the overview never loads them. Adding a shape adds one path here.
 */
export const GLYPHS: Record<ShapeId, string> = {
  layers: "M3 4h14M3 10h14M3 16h14",
  onion:
    "M10 10m-2 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0M10 10m-5 0a5 5 0 1 0 10 0a5 5 0 1 0-10 0M10 10m-8 0a8 8 0 1 0 16 0a8 8 0 1 0-16 0",
  stack: "M5 3v14h10V3M7 13h6M7 9h6M10 1v4",
  queue: "M2 7h16v6H2zM6 7v6M10 7v6M14 7v6",
  pipeline: "M2 8h4v4H2zM8 8h4v4H8zM14 8h4v4h-4zM6 10h2M12 10h2",
  cycle: "M10 3a7 7 0 1 1-6.1 3.6M3.9 2.5v4.1H8",
  hub: "M10 10m-2.5 0a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0-5 0M10 7.5V2M12.5 10H18M10 12.5V18M7.5 10H2",
  tree: "M8 2h4v3H8zM3 15h4v3H3zM13 15h4v3h-4zM10 5v5M5 10h10M5 10v5M15 10v5",
  mesh: "M4 4l12 3-8 9zM4 4l4 12M16 7L8 16M4 4m-1.5 0a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0",
  matrix: "M2 2h16v16H2zM10 2v16M2 10h16",
};
