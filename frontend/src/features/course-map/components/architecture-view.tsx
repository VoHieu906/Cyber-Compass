"use client";

import { useTranslations } from "next-intl";
import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from "react";
import type { Box, Rect } from "@/lib/graph/shapes/geometry";
import { layoutShape } from "@/lib/graph/shapes/registry";
import type { Architecture } from "@/lib/graph/shapes/schemas";
import { pressed } from "@/lib/shortcuts/registry";
import { cn } from "@/lib/utils";
import { EdgeMarkers, EdgePath, partKind, type EdgeKind } from "./edge-style";

/**
 * A lesson's architecture drawn in its shape (F3, D59): decorations and connectors as static SVG,
 * sub-nodes as real buttons over it (D27). Arrow keys move between the parts in their order; the
 * caller shows what the chosen part does. Relations between parts are routed around the other
 * parts (lib/graph/shapes/route.ts); hovering or focusing a part keeps its own relations and the
 * parts at their other ends at full strength and dims the rest. Used by the course map's focus mode and by the admin
 * Architecture panel's live preview, so authors see exactly what learners get.
 */
export function ArchitectureView({
  architecture,
  box,
  selected,
  onSelect,
  label,
  onLayout,
  onHot,
  only = null,
}: {
  architecture: Architecture & { shape: NonNullable<Architecture["shape"]> };
  box: Box;
  selected: string | null;
  onSelect: (id: string) => void;
  label: string;
  /** Reports where the parts sit, for edges drawn outside this box. */
  onLayout?: (parts: Map<string, Rect>) => void;
  /** The part under the pointer or keyboard focus, for edges drawn outside this box. */
  onHot?: (id: string | null) => void;
  /** A legend row's kind: only those lines stay, heavier; the rest dims. */
  only?: EdgeKind | null;
}) {
  const t = useTranslations("courseMap");
  const marker = `arch-${useId().replace(/:/g, "")}`;
  const layout = useMemo(
    () =>
      layoutShape(
        architecture.shape,
        { nodes: architecture.nodes, edges: architecture.edges, rows: architecture.rows, cols: architecture.cols },
        box,
      ),
    [architecture, box],
  );
  const report = useRef(onLayout);
  useEffect(() => {
    report.current = onLayout;
  });
  useEffect(() => report.current?.(layout.nodes), [layout]);
  useEffect(() => {
    if (process.env.NODE_ENV !== "production")
      for (const e of layout.edges.filter((e) => e.fallback))
        console.debug(`[course map] no clean route for ${e.from} -> ${e.to}; drawn beneath the parts`);
  }, [layout]);
  // Hover wins over keyboard focus while the pointer is on a part.
  const [hovered, setHovered] = useState<string | null>(null);
  const [focusedPart, setFocusedPart] = useState<string | null>(null);
  const hot = hovered ?? focusedPart;
  const hotRef = useRef(onHot);
  useEffect(() => {
    hotRef.current = onHot;
  });
  useEffect(() => hotRef.current?.(hot), [hot]);
  const near = useMemo(
    () =>
      hot
        ? new Set([hot, ...layout.edges.flatMap((e) => (e.from === hot ? [e.to] : e.to === hot ? [e.from] : []))])
        : null,
    [hot, layout],
  );
  const buttons = useRef(new Map<string, HTMLButtonElement>());
  const ids = architecture.nodes.map((n) => n.id);
  const active = selected ?? ids[0];

  const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>, id: string) => {
    const step =
      pressed(event, "courseMap.forward") || pressed(event, "courseMap.next")
        ? 1
        : pressed(event, "courseMap.backward") || pressed(event, "courseMap.previous")
          ? -1
          : 0;
    if (!step) return;
    event.preventDefault();
    event.stopPropagation();
    const next = ids[(ids.indexOf(id) + step + ids.length) % ids.length];
    buttons.current.get(next)?.focus();
  };

  return (
    <div role="group" aria-label={label} className="relative mx-auto" style={{ width: box.width, height: box.height }}>
      <svg width={box.width} height={box.height} className="absolute inset-0 overflow-visible" aria-hidden>
        <EdgeMarkers prefix={marker} />
        {layout.decorations.map((d, i) => {
          if (d.kind === "ellipse")
            return (
              <ellipse
                key={i}
                cx={d.cx}
                cy={d.cy}
                rx={d.rx}
                ry={d.ry}
                fill="none"
                style={{ stroke: "var(--color-rule-2)" }}
                strokeWidth={1.25}
              />
            );
          if (d.kind === "path")
            return <path key={i} d={d.d} fill="none" style={{ stroke: "var(--color-rule-2)" }} strokeWidth={1.5} />;
          return (
            <text
              key={i}
              x={d.x}
              y={d.y}
              textAnchor={d.anchor}
              dominantBaseline="central"
              fontSize={d.kind === "header" ? 12 : 11}
              style={{
                fill: d.kind === "header" ? "var(--color-ink-2)" : "var(--color-muted)",
                fontFamily: d.kind === "marker" ? "var(--font-measured)" : undefined,
              }}
            >
              {d.kind === "header" ? d.text : t(`markers.${d.key}`)}
            </text>
          );
        })}
        {layout.edges.map((e, i) => {
          const kind = partKind(e);
          // A legend row wins over a hovered part; either keeps its own lines and dims the rest.
          const on = only ? kind === only : hot !== null ? e.from === hot || e.to === hot : null;
          const lit = on === true;
          return (
            <g
              key={i}
              data-edge={`${e.from}>${e.to}`}
              data-state={on === null ? undefined : lit ? "lit" : "dim"}
              className="[transition:opacity_var(--dur-micro)] motion-reduce:transition-none"
              style={{ opacity: on === false ? 0.3 : 1 }}
            >
              <EdgePath kind={kind} d={e.d} prefix={marker} emphasis={lit} gap="var(--color-paper-2)" />
              {e.label && e.labelAt ? (
                <text
                  x={e.labelAt.x}
                  y={e.labelAt.y - 7}
                  textAnchor="middle"
                  fontSize={11}
                  style={{
                    fill: lit ? "var(--color-ink)" : "var(--color-ink-2)",
                    paintOrder: "stroke",
                    stroke: "var(--color-paper-2)",
                    strokeWidth: 4,
                  }}
                >
                  {e.label}
                </text>
              ) : null}
            </g>
          );
        })}
      </svg>
      {architecture.nodes.map((node) => {
        const r = layout.nodes.get(node.id)!;
        const isSelected = selected === node.id;
        return (
          <button
            key={node.id}
            ref={(el) => void (el ? buttons.current.set(node.id, el) : buttons.current.delete(node.id))}
            type="button"
            tabIndex={node.id === active ? 0 : -1}
            aria-pressed={isSelected}
            onClick={() => onSelect(node.id)}
            onKeyDown={(e) => onKeyDown(e, node.id)}
            onPointerEnter={() => setHovered(node.id)}
            onPointerLeave={() => setHovered(null)}
            onFocus={() => setFocusedPart(node.id)}
            onBlur={() => setFocusedPart(null)}
            data-state={near ? (near.has(node.id) ? "lit" : "dim") : undefined}
            className={cn(
              "absolute flex items-center justify-center overflow-hidden rounded-control border px-2 text-center text-[12px] leading-[14px] text-ink transition-[color,background-color,border-color,opacity] duration-[var(--dur-micro)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus motion-reduce:transition-none",
              near && !near.has(node.id) && "opacity-30",
              isSelected ? "border-accent bg-accent-soft" : "border-rule-2 bg-paper-2 hover:border-ink-2",
              node.section || node.note || node.lesson ? "cursor-pointer" : "",
            )}
            style={{ left: r.x, top: r.y, width: r.width, height: r.height }}
          >
            {/* Wrapped lines may end in "…"; the full label is the name. */}
            <span aria-hidden>
              {r.lines.map((line, i) => (
                <span key={i} className="block">
                  {line}
                </span>
              ))}
            </span>
            <span className="sr-only">{node.label}</span>
            {node.lesson || node.section ? (
              <span aria-hidden className="absolute top-1 right-1 size-1.5 rounded-full bg-accent" />
            ) : null}
          </button>
        );
      })}
    </div>
  );
}
