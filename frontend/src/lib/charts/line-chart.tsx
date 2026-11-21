"use client";

import { cn } from "@/lib/utils";
import { ChartTooltip, useActivePoint, YAxis, type Datum } from "./chart-parts";
import { labelEvery, niceTicks, yScale } from "./scale";
import { useWidth } from "./use-width";

type Props = {
  label: string;
  data: Datum[];
  formatTick: (value: number) => string;
  /** A fixed top for the y axis (100 for percentages); otherwise a clean value above the data. */
  max?: number;
  floor?: number;
  height?: number;
  className?: string;
};

const MARGIN = { top: 14, bottom: 24, left: 44 };
/** Room for the end label: about 7px per character of the 12px label, plus the gap after the dot. */
const endRoom = (label: string) => Math.max(24, label.length * 7 + 14);

/**
 * One series as a 2px line over a 10% wash (D32). The last point carries a dot and its value;
 * a crosshair and tooltip follow the pointer or the arrow keys to the nearest point.
 */
export function LineChart({ label, data, formatTick, max, floor = 0, height = 184, className }: Props) {
  const [ref, width] = useWidth<HTMLDivElement>();
  const right = endRoom(data[data.length - 1]?.display ?? "");
  const plotWidth = Math.max(0, width - MARGIN.left - right);
  const step = data.length > 1 ? plotWidth / (data.length - 1) : 0;
  const x = (i: number) => (data.length > 1 ? MARGIN.left + step * i : MARGIN.left + plotWidth / 2);
  const ticks = max ? niceTicks(max, 4).filter((t) => t <= max) : niceTicks(Math.max(0, ...data.map((d) => d.value)), 4, floor);
  const top = max ?? ticks[ticks.length - 1];
  const y = yScale(top, MARGIN.top, height - MARGIN.bottom);
  const baseline = height - MARGIN.bottom;
  const showLabel = labelEvery(data.length, data.length > 1 ? step : plotWidth);
  const { active, bind } = useActivePoint(data.length, (px) => Math.round((px - MARGIN.left) / Math.max(1, step)));

  const points = data.map((d, i) => `${x(i)},${y(d.value)}`);
  const line = points.length ? `M${points.join(" L")}` : "";
  const area = points.length > 1 ? `${line} L${x(data.length - 1)},${baseline} L${x(0)},${baseline} Z` : "";
  const last = data.length - 1;

  return (
    <div ref={ref} className={cn("relative", className)}>
      <div
        role="group"
        aria-label={label}
        className="relative rounded-control outline-none focus-visible:ring-2 focus-visible:ring-focus"
        style={{ height }}
        {...bind}
      >
        {width > 0 && data.length ? (
          <svg width={width} height={height} aria-hidden className="block overflow-visible">
            <YAxis ticks={ticks} y={y} left={MARGIN.left} right={width - right} format={formatTick} />
            <line x1={MARGIN.left} x2={width - right} y1={baseline} y2={baseline} className="stroke-rule-2" strokeWidth={1} shapeRendering="crispEdges" />
            {area ? <path d={area} className="fill-accent/10" /> : null}
            {active !== null ? <line x1={x(active)} x2={x(active)} y1={MARGIN.top} y2={baseline} className="stroke-ink-2" strokeWidth={1} shapeRendering="crispEdges" /> : null}
            <path d={line} fill="none" className="stroke-accent" strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
            {data.map((d, i) =>
              showLabel(i) ? (
                <text key={d.key} x={x(i)} y={height - 6} textAnchor="middle" className={cn("text-[11px]", i === last ? "fill-ink" : "fill-muted")}>
                  {d.label}
                </text>
              ) : null,
            )}
            {[last, ...(active !== null && active !== last ? [active] : [])].map((i) => (
              <circle key={i} cx={x(i)} cy={y(data[i].value)} r={4} className="fill-accent stroke-paper-2" strokeWidth={2} />
            ))}
            <text x={x(last) + 8} y={y(data[last].value)} dy="0.32em" className="fill-ink text-[12px] font-semibold tabular-nums">
              {data[last].display}
            </text>
          </svg>
        ) : null}
        <ChartTooltip datum={active !== null ? data[active] : null} x={active !== null ? x(active) : 0} width={width} />
      </div>
    </div>
  );
}
