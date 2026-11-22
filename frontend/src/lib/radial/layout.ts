/**
 * Geometry for the compass menu (D51). Pure: no React, no DOM. Labels are drawn level at
 * their anchor, so they stay upright on every bearing without rotating. Angles are in degrees,
 * 0 pointing up (north) and growing clockwise, so they read like a compass bearing.
 */

export type RingOptions = {
  innerR: number;
  outerR: number;
  /** Empty space between neighbouring segments. */
  gapDeg: number;
  /** Bearing where the first segment starts. */
  startDeg: number;
  /** Arc the ring covers; 360 for a full ring. */
  spanDeg?: number;
};

export type Segment = {
  /** SVG path of the annular sector, centred on (0, 0). */
  path: string;
  /** Bearing of the segment's middle. */
  centerDeg: number;
  startDeg: number;
  endDeg: number;
  /** Middle of the segment (mid radius), where the icon and label sit. */
  anchor: { x: number; y: number };
};

const round = (n: number) => Math.round(n * 100) / 100 + 0; // + 0 turns -0 into 0

export function point(r: number, deg: number) {
  const rad = (deg * Math.PI) / 180;
  return { x: round(Math.sin(rad) * r), y: round(-Math.cos(rad) * r) };
}

function sectorPath(innerR: number, outerR: number, from: number, to: number) {
  const sweep = to - from;
  // A full circle cannot be one arc command (start and end coincide): draw it as two halves.
  if (sweep >= 359.999) {
    const o1 = point(outerR, from), o2 = point(outerR, from + 180), i1 = point(innerR, from), i2 = point(innerR, from + 180);
    return `M${o1.x} ${o1.y}A${outerR} ${outerR} 0 1 1 ${o2.x} ${o2.y}A${outerR} ${outerR} 0 1 1 ${o1.x} ${o1.y}M${i1.x} ${i1.y}A${innerR} ${innerR} 0 1 0 ${i2.x} ${i2.y}A${innerR} ${innerR} 0 1 0 ${i1.x} ${i1.y}Z`;
  }
  const large = sweep > 180 ? 1 : 0;
  const o1 = point(outerR, from), o2 = point(outerR, to), i1 = point(innerR, to), i2 = point(innerR, from);
  return `M${o1.x} ${o1.y}A${outerR} ${outerR} 0 ${large} 1 ${o2.x} ${o2.y}L${i1.x} ${i1.y}A${innerR} ${innerR} 0 ${large} 0 ${i2.x} ${i2.y}Z`;
}

const normalize = (deg: number) => ((deg % 360) + 360) % 360;

/**
 * `count` equal segments over `spanDeg`: each spans spanDeg / count minus the gap, so the
 * segments and gaps together always cover the span exactly, for any count.
 */
export function layoutRing(count: number, { innerR, outerR, gapDeg, startDeg, spanDeg = 360 }: RingOptions): Segment[] {
  if (count <= 0) return [];
  const span = Math.min(360, spanDeg);
  const step = span / count;
  // One item on a full ring has nothing to separate from.
  const gap = count === 1 && span === 360 ? 0 : Math.min(gapDeg, step / 2);
  const mid = (innerR + outerR) / 2;
  return Array.from({ length: count }, (_, i) => {
    const from = startDeg + i * step + gap / 2;
    const to = startDeg + (i + 1) * step - gap / 2;
    const centerDeg = normalize((from + to) / 2);
    return { path: sectorPath(innerR, outerR, from, to), centerDeg, startDeg: from, endDeg: to, anchor: point(mid, centerDeg) };
  });
}

/**
 * A submenu ring: an arc centred on its parent's bearing, `minChildDeg` per child, clamped to
 * 360 so it never overlaps itself. Deeper levels use the same rule.
 */
export function layoutChildRing(count: number, parentDeg: number, opts: Omit<RingOptions, "startDeg" | "spanDeg"> & { minChildDeg: number }) {
  const spanDeg = Math.min(360, count * opts.minChildDeg);
  return layoutRing(count, { ...opts, spanDeg, startDeg: parentDeg - spanDeg / 2 });
}

/** Radii of ring `level` (0 = top level): rings stack outward with a small gap. */
export function ringRadii(level: number, { hubR, width, gap }: { hubR: number; width: number; gap: number }) {
  const innerR = hubR + gap + level * (width + gap);
  return { innerR, outerR: innerR + width };
}

/**
 * Where to put the compass centre so a circle of `radius` stays inside the box: as close to the
 * trigger as fits, which moves it toward the middle of the screen. Also the scale needed when
 * the box is smaller than the circle (phones).
 */
export function placeCompass(anchor: { x: number; y: number }, radius: number, box: { left: number; top: number; right: number; bottom: number }) {
  const scale = Math.min(1, (box.right - box.left) / (2 * radius), (box.bottom - box.top) / (2 * radius));
  const r = radius * scale;
  const clamp = (v: number, lo: number, hi: number) => (lo > hi ? (lo + hi) / 2 : Math.min(hi, Math.max(lo, v)));
  return { x: clamp(anchor.x, box.left + r, box.right - r), y: clamp(anchor.y, box.top + r, box.bottom - r), scale };
}
