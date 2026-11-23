import type { Box } from "./geometry";
import type { ShapeId } from "./schemas";

/** The footprint a focused lesson's shape is drawn in, by available width (the detail strip sits below it). */
export const SHAPE_BOX: Record<"wide" | "compact", Box> = {
  wide: { width: 600, height: 340 },
  compact: { width: 480, height: 320 },
};

/**
 * The height a shape needs inside a footprint, so a four-stage pipeline does not sit in a box
 * built for a twelve-node tree. Kept apart from the layouts (and tiny) because the course map
 * needs it to make room before the focus panel, which holds the layouts, has loaded.
 */
export function fitBox(shape: ShapeId | null, count: number, rows: number, box: Box): Box {
  const height = (() => {
    switch (shape) {
      case null:
        return 120;
      case "pipeline":
        return count > 5 ? 200 : 110;
      case "queue":
        return count > 5 ? 220 : 130;
      case "layers":
        return 16 + count * 52;
      case "stack":
        return 36 + count * 46;
      case "matrix":
        return 40 + rows * 86;
      default:
        return box.height;
    }
  })();
  return { width: box.width, height: Math.min(box.height, height) };
}
