"use client";

import { cn } from "@/lib/utils";
import { ChartTooltip, useActivePoint, YAxis, type Datum } from "./chart-parts";
import { columnPath, labelEvery, niceTicks, yScale } from "./scale";
import { useWidth } from "./use-width";

type Props = {
  /** Accessible name of the chart, such as "Minutes studied per week". */
  label: string;
  data: Datum[];
  formatTick: (value: number) => string;
  /** A solid hairline across the plot, such as a weekly goal, with its own label. */
  reference?: { value: number; label: string };
  /** The y axis reaches at least this value, so an empty series still has a scale. */
  floor?: number;
  /** Tick values for a given data maximum; clean 1-2-5 steps by default. */
  tickValues?: (max: number) => number[];
  height?: number;
  className?: string;
};

const MARGIN = { top: 14, right: 4, bottom: 24, left: 44 };

/**
 * One series of columns (D32): at most 24px wide, a 4px rounded data end and a square foot on
 * the baseline, hairline grid, one tab stop with a tooltip per column. A zero draws nothing.
 */
export function ColumnChart({ label, data, formatTick, reference, floor = 0, tickValues, height = 184, className }: Props) {
  const [ref, width] = useWidth<HTMLDivElement>();
  const plotWidth = Math.max(0, width - MARGIN.left - MARGIN.right);
  const band = data.length ? plotWidth / data.length : 0;
  const barWidth = Math.max(2, Math.min(24, band * 0.6));
  const dataMax = Math.max(0, ...data.map((d) => d.value), reference?.value ?? 0);
  const ticks = tickValues ? tickValues(Math.max(dataMax, floor)) : niceTicks(dataMax, 4, floor);
  const y = yScale(ticks[ticks.length - 1], MARGIN.top, height - MARGIN.bottom);
  const baseline = height - MARGIN.bottom;
  const showLabel = labelEvery(data.length, band);
  const center = (i: number) => MARGIN.left + band * i + band / 2;
  const { active, bind } = useActivePoint(data.length, (x) => Math.floor((x - MARGIN.left) / Math.max(1, band)));

  return (
    <div ref={ref} className={cn("relative", className)}>
      <div
        role="group"
        aria-label={label}
        className="relative rounded-control outline-none focus-visible:ring-2 focus-visible:ring-focus"
        style={{ height }}
        {...bind}
      >
        {width > 0 ? (
          <svg width={width} height={height} aria-hidden className="block overflow-visible">
            <YAxis ticks={ticks} y={y} left={MARGIN.left} right={width - MARGIN.right} format={formatTick} />
            {data.map((d, i) => (
              <g key={d.key}>
                {active === i ? <rect x={center(i) - band / 2} y={MARGIN.top} width={band} height={baseline - MARGIN.top} className="fill-ink/5" /> : null}
                <path d={columnPath(center(i) - barWidth / 2, barWidth, y(d.value), baseline)} className="fill-accent" />
                {showLabel(i) ? (
                  <text x={center(i)} y={height - 6} textAnchor="middle" className={cn("text-[11px]", i === data.length - 1 ? "fill-ink" : "fill-muted")}>
                    {d.label}
                  </text>
                ) : null}
              </g>
            ))}
            {reference && reference.value > 0 ? (
              <g>
                <line x1={MARGIN.left} x2={width - MARGIN.right} y1={y(reference.value)} y2={y(reference.value)} className="stroke-ink-2" strokeWidth={1} shapeRendering="crispEdges" />
                <text x={width - MARGIN.right} y={y(reference.value) - 5} textAnchor="end" className="fill-ink-2 text-[11px]">
                  {reference.label}
                </text>
              </g>
            ) : null}
            <line x1={MARGIN.left} x2={width - MARGIN.right} y1={baseline} y2={baseline} className="stroke-rule-2" strokeWidth={1} shapeRendering="crispEdges" />
          </svg>
        ) : null}
        <ChartTooltip datum={active !== null ? data[active] : null} x={active !== null ? center(active) : 0} width={width} />
      </div>
    </div>
  );
}
