import { GLYPHS } from "@/lib/graph/shapes/glyphs";
import type { ShapeId } from "@/lib/graph/shapes/schemas";
import { cn } from "@/lib/utils";

/** A shape's small glyph (decorative: the shape's name is always given in text beside it). */
export function ShapeGlyph({ shape, className }: { shape: ShapeId; className?: string }) {
  return (
    <svg
      viewBox="0 0 20 20"
      aria-hidden
      className={cn("size-5 shrink-0", className)}
      fill="none"
      stroke="currentColor"
      strokeWidth={1.5}
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d={GLYPHS[shape]} />
    </svg>
  );
}
