"use client";

import { useState, type KeyboardEvent, type PointerEvent } from "react";
import { cn } from "@/lib/utils";

/** One point or column. `label` sits on the axis; `detail` and `display` fill the tooltip and table. */
export type Datum = {
  key: string;
  label: string;
  detail: string;
  value: number;
  display: string;
};

export type ChartTableText = {
  /** The disclosure's label, such as "Show as a table". */
  toggle: string;
  /** Column headers: what each row is, and its value. */
  columns: [string, string];
};

/**
 * The point under the pointer or keyboard. The chart is one tab stop: arrow keys, Home and End
 * move between points; leaving it clears the readout. `indexAt` maps a pointer x (relative to the
 * chart) to a point.
 */
export function useActivePoint(count: number, indexAt: (x: number) => number) {
  const [active, setActive] = useState<number | null>(null);
  const clamp = (i: number) => Math.max(0, Math.min(count - 1, i));

  return {
    active: active !== null && active < count ? active : null,
    bind: {
      tabIndex: count ? 0 : -1,
      onPointerMove: (event: PointerEvent<HTMLElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();
        setActive(clamp(indexAt(event.clientX - rect.left)));
      },
      onPointerLeave: () => setActive(null),
      onFocus: () => setActive((current) => current ?? count - 1),
      onBlur: () => setActive(null),
      onKeyDown: (event: KeyboardEvent<HTMLElement>) => {
        const moves: Record<string, (i: number) => number> = {
          ArrowLeft: (i) => i - 1,
          ArrowRight: (i) => i + 1,
          Home: () => 0,
          End: () => count - 1,
        };
        const move = moves[event.key];
        if (!move) return;
        event.preventDefault();
        setActive((current) => clamp(move(current ?? count - 1)));
      },
    },
  };
}

/** The readout over the active point: the value leads, the label follows. Announced politely. */
export function ChartTooltip({ datum, x, width }: { datum: Datum | null; x: number; width: number }) {
  const left = Math.max(64, Math.min(width - 64, x));
  return (
    <>
      <div aria-live="polite" className="sr-only">
        {datum ? `${datum.detail}: ${datum.display}` : ""}
      </div>
      {datum ? (
        <div
          aria-hidden
          className="pointer-events-none absolute top-0 z-10 flex -translate-x-1/2 -translate-y-[calc(100%+4px)] flex-col whitespace-nowrap rounded-control border border-rule bg-paper-2 px-2.5 py-1.5 text-sm shadow-sm"
          style={{ left }}
        >
          <span className="measured font-semibold text-ink">{datum.display}</span>
          <span className="text-muted">{datum.detail}</span>
        </div>
      ) : null}
    </>
  );
}

/** The table view every chart carries, so no value depends on hovering or on colour. */
export function ChartTable({ data, text, className }: { data: Datum[]; text: ChartTableText; className?: string }) {
  return (
    <details className={cn("group text-sm", className)}>
      <summary className="w-fit cursor-pointer text-ink-2 underline decoration-rule-2 underline-offset-4 hover:decoration-accent">{text.toggle}</summary>
      <table className="mt-3 w-full max-w-[28rem] border-collapse">
        <thead>
          <tr className="border-b border-rule text-left text-muted">
            <th scope="col" className="py-1.5 pr-4 font-medium">
              {text.columns[0]}
            </th>
            <th scope="col" className="py-1.5 text-right font-medium">
              {text.columns[1]}
            </th>
          </tr>
        </thead>
        <tbody>
          {data.map((d) => (
            <tr key={d.key} className="border-b border-rule last:border-0">
              <th scope="row" className="py-1.5 pr-4 text-left font-normal text-ink-2">
                {d.detail}
              </th>
              <td className="measured py-1.5 text-right tabular-nums text-ink">{d.display}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </details>
  );
}

/** Y-axis tick labels and hairline gridlines, shared by both chart kinds. */
export function YAxis({ ticks, y, left, right, format }: { ticks: number[]; y: (v: number) => number; left: number; right: number; format: (v: number) => string }) {
  return (
    <g>
      {ticks.map((tick) => (
        <g key={tick}>
          <line x1={left} x2={right} y1={y(tick)} y2={y(tick)} className="stroke-rule" strokeWidth={1} shapeRendering="crispEdges" />
          <text x={left - 8} y={y(tick)} dy="0.32em" textAnchor="end" className="fill-muted text-[11px] tabular-nums">
            {format(tick)}
          </text>
        </g>
      ))}
    </g>
  );
}
