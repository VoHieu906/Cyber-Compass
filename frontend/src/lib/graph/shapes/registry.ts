import type { Box } from "./geometry";
import { GLYPHS } from "./glyphs";
import { SHAPE_BOX } from "./sizes";
import { LAYOUTS, type ShapeInput, type ShapeLayout } from "./layouts";
import { SHAPES, SHAPE_LIMITS, type ShapeId } from "./schemas";

/*
 * The architecture shape registry (F3, D59): shape id → limits, layout and glyph. The backend
 * twin is MapSchemas::SHAPES with one JSON Schema per shape; a test fails when either
 * side has a shape the other lacks (contract.test.ts). Adding a shape: an entry in SHAPES (schemas.ts), its layout
 * in layouts.ts, its glyph in glyphs.ts, and the backend schema and fixtures.
 */

export const SHAPE_REGISTRY = Object.fromEntries(
  SHAPES.map((id) => [id, { id, limits: SHAPE_LIMITS[id], layout: LAYOUTS[id], glyph: GLYPHS[id] }]),
) as Record<
  ShapeId,
  {
    id: ShapeId;
    limits: { min: number; max: number };
    layout: (input: ShapeInput, box: Box) => ShapeLayout;
    glyph: string;
  }
>;

export function layoutShape(shape: ShapeId, input: ShapeInput, box: Box): ShapeLayout {
  return SHAPE_REGISTRY[shape].layout(input, box);
}

export { SHAPES, SHAPE_BOX, type ShapeId, type ShapeInput, type ShapeLayout };
