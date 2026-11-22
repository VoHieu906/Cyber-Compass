import { describe, expect, it } from "vitest";
import { layoutChildRing, layoutRing, placeCompass, point, ringRadii } from "./layout";

const ring = { innerR: 56, outerR: 132, gapDeg: 2, startDeg: 0 };

describe("layoutRing", () => {
  it.each(Array.from({ length: 16 }, (_, i) => i + 1))("covers the full ring with %i segments", (count) => {
    const segments = layoutRing(count, ring);
    expect(segments).toHaveLength(count);
    const step = 360 / count;
    const gap = count === 1 ? 0 : Math.min(2, step / 2);
    segments.forEach((s, i) => {
      // Each segment spans 360 / count minus the gap, and they are evenly spaced.
      expect(s.endDeg - s.startDeg).toBeCloseTo(step - gap, 6);
      expect(s.startDeg).toBeCloseTo(i * step + gap / 2, 6);
      expect(s.path).toMatch(/^M.*Z$/);
    });
    const covered = segments.reduce((sum, s) => sum + (s.endDeg - s.startDeg), 0) + gap * count;
    expect(covered).toBeCloseTo(360, 6);
    // No two segments overlap.
    for (let i = 1; i < count; i++) expect(segments[i].startDeg).toBeGreaterThanOrEqual(segments[i - 1].endDeg);
  });

  it("draws one item as a complete ring", () => {
    const [only] = layoutRing(1, ring);
    expect(only.endDeg - only.startDeg).toBe(360);
    expect(only.path.match(/A/g)).toHaveLength(4);
  });

  it("puts anchors at mid radius on the centre bearing, 0° pointing up", () => {
    const [north, east, , west] = layoutRing(4, { ...ring, startDeg: -45 });
    expect(north.centerDeg).toBe(0);
    expect(north.anchor).toEqual({ x: 0, y: -94 });
    expect(east.anchor).toEqual({ x: 94, y: 0 });
    expect(west.centerDeg).toBe(270);
  });

  it("returns nothing for no items", () => {
    expect(layoutRing(0, ring)).toEqual([]);
  });
});

describe("layoutChildRing", () => {
  it("centres the arc on the parent and gives each child its minimum angle", () => {
    const children = layoutChildRing(3, 90, { innerR: 140, outerR: 200, gapDeg: 2, minChildDeg: 30 });
    expect(children[1].centerDeg).toBeCloseTo(90, 6);
    expect(children[0].startDeg).toBeCloseTo(90 - 45 + 1, 6);
    expect(children[2].endDeg).toBeCloseTo(90 + 45 - 1, 6);
  });

  it("clamps to a full ring, so a large submenu never overlaps itself", () => {
    for (const count of [12, 13, 16, 40]) {
      const children = layoutChildRing(count, 200, { innerR: 140, outerR: 200, gapDeg: 2, minChildDeg: 30 });
      const first = children[0].startDeg;
      const last = children[children.length - 1].endDeg;
      expect(last - first).toBeLessThanOrEqual(360);
    }
  });
});

describe("ringRadii and placement", () => {
  it("stacks rings outward", () => {
    expect(ringRadii(0, { hubR: 50, width: 70, gap: 6 })).toEqual({ innerR: 56, outerR: 126 });
    expect(ringRadii(2, { hubR: 50, width: 70, gap: 6 })).toEqual({ innerR: 208, outerR: 278 });
  });

  it("moves the centre from a corner trigger toward the middle, and scales down on small screens", () => {
    const box = { left: 0, top: 64, right: 1280, bottom: 800 };
    expect(placeCompass({ x: 1250, y: 770 }, 200, box)).toEqual({ x: 1080, y: 600, scale: 1 });
    const phone = placeCompass({ x: 360, y: 800 }, 260, { left: 8, top: 72, right: 382, bottom: 836 });
    expect(phone.scale).toBeCloseTo(374 / 520, 6);
    expect(phone.x).toBeCloseTo(195, 6);
  });

  it("points the right way", () => {
    expect(point(10, 90)).toEqual({ x: 10, y: 0 });
    expect(point(10, 180)).toEqual({ x: 0, y: 10 });
  });
});
