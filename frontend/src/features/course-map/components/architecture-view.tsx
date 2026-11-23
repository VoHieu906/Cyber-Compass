"use client";

import { useTranslations } from "next-intl";
import { useId, useMemo, useRef, type KeyboardEvent } from "react";
import type { Box } from "@/lib/graph/shapes/geometry";
import { layoutShape } from "@/lib/graph/shapes/registry";
import type { Architecture } from "@/lib/graph/shapes/schemas";
import { pressed } from "@/lib/shortcuts/registry";
import { cn } from "@/lib/utils";

/**
 * A lesson's architecture drawn in its shape (F3, D59): decorations and connectors as static SVG,
 * sub-nodes as real buttons over it (D27). Arrow keys move between the parts in their order; the
 * caller shows what the chosen part does. Used by the course map's focus mode and by the admin
 * Architecture panel's live preview, so authors see exactly what learners get.
 */
export function ArchitectureView({
  architecture,
  box,
  selected,
  onSelect,
  label,
}: {
  architecture: Architecture & { shape: NonNullable<Architecture["shape"]> };
  box: Box;
  selected: string | null;
  onSelect: (id: string) => void;
  label: string;
}) {
  const t = useTranslations("courseMap");
  const marker = `arch-arrow-${useId().replace(/:/g, "")}`;
  const layout = useMemo(
    () =>
      layoutShape(
        architecture.shape,
        { nodes: architecture.nodes, edges: architecture.edges, rows: architecture.rows, cols: architecture.cols },
        box,
      ),
    [architecture, box],
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
      <svg width={box.width} height={box.height} className="absolute inset-0" aria-hidden>
        <defs>
          <marker
            id={marker}
            viewBox="0 0 10 10"
            refX="9"
            refY="5"
            markerWidth="7"
            markerHeight="7"
            orient="auto-start-reverse"
          >
            <path d="M 0 0 L 10 5 L 0 10 z" style={{ fill: "var(--color-muted)" }} />
          </marker>
        </defs>
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
        {layout.edges.map((e, i) => (
          <g key={i}>
            <path
              d={e.d}
              fill="none"
              style={{ stroke: e.implicit ? "var(--color-rule-2)" : "var(--color-muted)" }}
              strokeWidth={e.implicit ? 1.25 : 1.5}
              strokeDasharray={e.implicit ? undefined : "5 3"}
              markerEnd={e.directed ? `url(#${marker})` : undefined}
            />
            {e.label && e.labelAt ? (
              <text
                x={e.labelAt.x}
                y={e.labelAt.y - 7}
                textAnchor="middle"
                fontSize={11}
                style={{
                  fill: "var(--color-muted)",
                  paintOrder: "stroke",
                  stroke: "var(--color-paper-2)",
                  strokeWidth: 4,
                }}
              >
                {e.label}
              </text>
            ) : null}
          </g>
        ))}
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
            className={cn(
              "absolute flex items-center justify-center overflow-hidden rounded-control border px-2 text-center text-[12px] leading-[14px] text-ink transition-colors duration-[var(--dur-micro)]",
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
