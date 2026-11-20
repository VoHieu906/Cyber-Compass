"use client";

import { useId, useMemo, type KeyboardEvent } from "react";
import { cn } from "@/lib/utils";
import { EDGE_LABEL, edgePath, layoutGraph, textWidth, wrapLabel } from "./layout";

export type GraphShape = "box" | "pill" | "diamond";
export type GraphViewNode = {
  id: string;
  label: string;
  note?: string;
  shape?: GraphShape;
  emphasis?: boolean;
  selectable?: boolean;
};
export type GraphViewEdge = { from: string; to: string; label?: string };

const LINE = 17;
const PAD_X = 14;
const PAD_Y = 10;

function nodeSize(n: GraphViewNode) {
  const lines = wrapLabel(n.label);
  const textW = Math.max(...lines.map((l) => textWidth(l)), n.note ? textWidth(n.note, 11) : 0);
  let width = Math.max(88, textW + PAD_X * 2);
  let height = lines.length * LINE + (n.note ? 15 : 0) + PAD_Y * 2;
  if (n.shape === "diamond") {
    // The text box sits inside the diamond, so the diamond is about twice as big.
    width = Math.round(width * 1.45);
    height = Math.round(height * 1.6);
  }
  return { lines, width, height };
}

/**
 * A static, themed SVG of a laid-out graph. Nodes marked selectable are keyboard buttons that
 * report selection; everything else is decoration over the text alternative the caller renders.
 * Wide graphs scroll inside their own box rather than shrinking text below a legible size.
 */
export function GraphView({
  nodes,
  edges,
  direction = "TB",
  arrows = true,
  selected,
  onSelect,
  label,
}: {
  nodes: GraphViewNode[];
  edges: GraphViewEdge[];
  direction?: "TB" | "LR";
  arrows?: boolean;
  selected?: string | null;
  onSelect?: (id: string) => void;
  label: string;
}) {
  const markerId = `arrow-${useId().replace(/:/g, "")}`;
  const sized = useMemo(() => new Map(nodes.map((n) => [n.id, nodeSize(n)])), [nodes]);
  const graph = useMemo(
    () =>
      layoutGraph(
        nodes.map((n) => ({ id: n.id, ...sized.get(n.id)! })),
        edges,
        { direction, rankGap: edges.some((e) => e.label) ? 56 : 44 },
      ),
    [nodes, edges, direction, sized],
  );

  const keyDown = (id: string) => (event: KeyboardEvent<SVGGElement>) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      onSelect?.(id);
    }
  };

  return (
    <div className="overflow-x-auto rounded-control border border-rule bg-paper-3 p-2">
      <svg
        viewBox={`0 0 ${graph.width} ${graph.height}`}
        width={graph.width}
        role="group"
        aria-label={label}
        className="mx-auto block h-auto max-w-full"
        style={{ minWidth: Math.min(graph.width, 560) }}
      >
        <defs>
          <marker
            id={markerId}
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

        {graph.edges.map((e, i) => (
          <g key={i} aria-hidden>
            <path
              d={edgePath(e.points)}
              fill="none"
              style={{ stroke: "var(--color-rule-2)" }}
              strokeWidth={1.5}
              markerEnd={arrows ? `url(#${markerId})` : undefined}
            />
            {e.label && e.labelAt ? (
              <g transform={`translate(${e.labelAt.x} ${e.labelAt.y})`}>
                <rect
                  x={-Math.min(EDGE_LABEL.width, textWidth(e.label, 11) + 12) / 2}
                  y={-EDGE_LABEL.height / 2}
                  width={Math.min(EDGE_LABEL.width, textWidth(e.label, 11) + 12)}
                  height={EDGE_LABEL.height}
                  rx={4}
                  style={{ fill: "var(--color-paper-3)" }}
                />
                <text
                  textAnchor="middle"
                  dominantBaseline="central"
                  fontSize={11}
                  style={{ fill: "var(--color-muted)", fontFamily: "var(--font-measured)" }}
                >
                  {e.label}
                </text>
              </g>
            ) : null}
          </g>
        ))}

        {nodes.map((n) => {
          const at = graph.nodes.get(n.id)!;
          const { lines } = sized.get(n.id)!;
          const active = selected === n.id;
          const x = at.x - at.width / 2;
          const y = at.y - at.height / 2;
          const fill = active ? "var(--color-accent-soft)" : n.emphasis ? "var(--color-paper)" : "var(--color-paper-2)";
          const stroke = active ? "var(--color-accent)" : n.emphasis ? "var(--color-ink-2)" : "var(--color-rule-2)";
          const textTop = at.y - ((lines.length - 1) * LINE) / 2 - (n.note ? 7 : 0);
          const interactive = n.selectable && onSelect;
          return (
            <g
              key={n.id}
              {...(interactive
                ? {
                    role: "button",
                    tabIndex: 0,
                    "aria-pressed": active,
                    "aria-label": n.label,
                    onClick: () => onSelect(n.id),
                    onKeyDown: keyDown(n.id),
                    className:
                      "cursor-pointer outline-none [&:focus-visible>.node-shape]:[stroke-width:3px] [&:focus-visible>.node-shape]:[stroke:var(--color-accent)]",
                  }
                : { "aria-label": n.label, role: "img" })}
            >
              {n.shape === "diamond" ? (
                <polygon
                  className="node-shape"
                  points={`${at.x},${y} ${x + at.width},${at.y} ${at.x},${y + at.height} ${x},${at.y}`}
                  style={{ fill, stroke }}
                  strokeWidth={active ? 2 : 1.25}
                />
              ) : (
                <rect
                  className="node-shape"
                  x={x}
                  y={y}
                  width={at.width}
                  height={at.height}
                  rx={n.shape === "pill" ? at.height / 2 : 6}
                  style={{ fill, stroke }}
                  strokeWidth={active ? 2 : 1.25}
                />
              )}
              <text textAnchor="middle" fontSize={13} style={{ fill: "var(--color-ink)" }} aria-hidden>
                {lines.map((line, i) => (
                  <tspan key={i} x={at.x} y={textTop + i * LINE} dominantBaseline="central">
                    {line}
                  </tspan>
                ))}
                {n.note ? (
                  <tspan
                    x={at.x}
                    y={textTop + lines.length * LINE + 1}
                    dominantBaseline="central"
                    fontSize={11}
                    style={{ fill: "var(--color-muted)", fontFamily: "var(--font-measured)" }}
                  >
                    {n.note}
                  </tspan>
                ) : null}
              </text>
              {n.selectable && !active ? (
                <circle
                  cx={x + at.width - 8}
                  cy={y + 8}
                  r={2.5}
                  style={{ fill: "var(--color-accent)" }}
                  aria-hidden
                  className={cn(n.shape === "diamond" && "hidden")}
                />
              ) : null}
            </g>
          );
        })}
      </svg>
    </div>
  );
}
