import type { Point } from "../layout";
import { textWidth, wrapLabel } from "../text";

/*
 * Shared geometry of the shape layouts (F3, D59). Everything here is pure: sizes come from the
 * label length (no DOM measurement, as in lib/graph/layout.ts), so layouts are unit-tested and
 * identical on the server and in the browser.
 */

export type Rect = { x: number; y: number; width: number; height: number };
export type Box = { width: number; height: number };

export const PAD = 8;
export const FONT = 12;
export const LINE = 14;

/** A placed sub-node: its rectangle (top left) and its label broken into lines. */
export type PlacedSub = Rect & { lines: string[] };

/** Label lines that fit `width`, at most `maxLines`. */
export function fitLabel(label: string, width: number, maxLines = 2): string[] {
  const chars = Math.max(4, Math.floor((width - 14) / (FONT * 0.56)));
  return wrapLabel(label, chars, maxLines);
}

/** The height a label needs at `width`, capped to `max`. */
export function labelHeight(lines: string[], max = Infinity): number {
  return Math.min(max, lines.length * LINE + 14);
}

/** A sub-node box around a centre, as wide as its label needs up to `maxWidth`. */
export function boxAround(label: string, cx: number, cy: number, maxWidth: number, maxHeight = Infinity): PlacedSub {
  const lines = fitLabel(label, maxWidth, maxHeight >= 2 * LINE + 14 ? 2 : 1);
  const width = Math.min(maxWidth, Math.max(56, ...lines.map((l) => textWidth(l, FONT) + 18)));
  const height = labelHeight(lines, maxHeight);
  return { x: cx - width / 2, y: cy - height / 2, width, height, lines };
}

/** A sub-node filling a rectangle. */
export function boxIn(label: string, r: Rect, maxLines = 2): PlacedSub {
  const fits = Math.max(1, Math.floor((r.height - 14) / LINE));
  return { ...r, lines: fitLabel(label, r.width, Math.min(maxLines, fits)) };
}

export const centre = (r: Rect): Point => ({ x: r.x + r.width / 2, y: r.y + r.height / 2 });

export function overlaps(a: Rect, b: Rect, gap = 0): boolean {
  return (
    a.x < b.x + b.width + gap && b.x < a.x + a.width + gap && a.y < b.y + b.height + gap && b.y < a.y + a.height + gap
  );
}

/** Where the segment from the centre of `r` towards `to` leaves `r`. */
export function borderPoint(r: Rect, to: Point): Point {
  const c = centre(r);
  const dx = to.x - c.x;
  const dy = to.y - c.y;
  if (dx === 0 && dy === 0) return c;
  const sx = dx === 0 ? Infinity : r.width / 2 / Math.abs(dx);
  const sy = dy === 0 ? Infinity : r.height / 2 / Math.abs(dy);
  const s = Math.min(sx, sy);
  return { x: c.x + dx * s, y: c.y + dy * s };
}

/** A straight connector between two rectangles' borders, and its middle (for a label). */
export function connect(a: Rect, b: Rect): { d: string; mid: Point } {
  const p = borderPoint(a, centre(b));
  const q = borderPoint(b, centre(a));
  return { d: `M ${p.x} ${p.y} L ${q.x} ${q.y}`, mid: { x: (p.x + q.x) / 2, y: (p.y + q.y) / 2 } };
}
