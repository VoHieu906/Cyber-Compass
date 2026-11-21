/**
 * Pure helpers for the dashboard charts (D32). Kept apart from rendering, like lib/graph.
 */

/** A clean step (1, 2 or 5 times a power of ten) that splits `max` into about `count` intervals. */
export function niceStep(max: number, count: number): number {
  const raw = max / Math.max(1, count);
  if (!(raw > 0)) return 1;
  const magnitude = 10 ** Math.floor(Math.log10(raw));
  const residual = raw / magnitude;
  const factor = residual <= 1 ? 1 : residual <= 2 ? 2 : residual <= 5 ? 5 : 10;
  return factor * magnitude;
}

/**
 * Tick values from 0 up to the first clean value at or above `max`. `floor` keeps an empty or
 * tiny series from drawing an axis of 0 to 1 (a week with no study still shows 0 to 30 min).
 */
export function niceTicks(max: number, count = 4, floor = 0): number[] {
  const top = Math.max(max, floor);
  if (top <= 0) return [0, 1];
  const step = niceStep(top, count);
  const last = Math.ceil(top / step - 1e-9) * step;
  const ticks: number[] = [];
  for (let v = 0; v <= last + step / 2; v += step) ticks.push(Math.round(v * 1e6) / 1e6);
  return ticks;
}

/** Steps that read well as durations: minutes, then whole and half hours. */
export const MINUTE_STEPS = [5, 10, 15, 20, 30, 60, 90, 120, 180, 240, 360, 480, 600, 900, 1200];

/** Ticks from 0 in the smallest listed step that covers `max` in at most `count` intervals. */
export function stepTicks(max: number, steps: number[], count = 4, floor = 0): number[] {
  const top = Math.max(max, floor, 1);
  const step = steps.find((s) => Math.ceil(top / s - 1e-9) <= count) ?? niceStep(top, count);
  const ticks: number[] = [];
  for (let v = 0; v <= Math.ceil(top / step - 1e-9) * step; v += step) ticks.push(v);
  return ticks;
}

/** Maps a value in [0, domainMax] to a y position, 0 at the bottom of the plot. */
export function yScale(domainMax: number, top: number, bottom: number) {
  return (value: number) => (domainMax <= 0 ? bottom : bottom - (Math.min(value, domainMax) / domainMax) * (bottom - top));
}

/** Every k-th label, so x labels stay at least `minGap` px apart; the last is always shown. */
export function labelEvery(count: number, band: number, minGap = 52): (index: number) => boolean {
  const k = Math.max(1, Math.ceil(minGap / Math.max(1, band)));
  return (index) => (count - 1 - index) % k === 0;
}

/**
 * A column with a rounded data end and a square foot on the baseline, as an SVG path.
 * The corner radius never exceeds half the width or the column's own height.
 */
export function columnPath(x: number, width: number, top: number, baseline: number, radius = 4): string {
  const height = baseline - top;
  if (height <= 0) return "";
  const r = Math.min(radius, width / 2, height);
  return [
    `M${x},${baseline}`,
    `V${top + r}`,
    `Q${x},${top} ${x + r},${top}`,
    `H${x + width - r}`,
    `Q${x + width},${top} ${x + width},${top + r}`,
    `V${baseline}`,
    "Z",
  ].join(" ");
}
