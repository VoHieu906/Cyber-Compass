"use client";

import { Check, Circle, CircleDot } from "lucide-react";
import { useTranslations } from "next-intl";
import { useCallback, useId, useMemo, useState, type ReactNode } from "react";
import { Duration } from "@/features/catalog/components/labels";
import { gridEdgePath, layoutGrid, type GridMetrics } from "@/lib/graph/grid";
import { cn } from "@/lib/utils";
import type { PathLesson } from "../types";
import type { NodeStatus } from "./node-status";
import { RoadmapNodeCard } from "./node-status";

export type GraphTopic = {
  slug: string;
  title: string;
  status: NodeStatus;
  percent: number;
  row: number;
  col: number;
  review?: boolean;
  meta?: string;
  /** Read by screen readers with the node: why it is here, what it needs. */
  description: string;
  requires: string[];
  lessons: PathLesson[];
};

type Props = {
  label: string;
  topics: GraphTopic[];
  rowCount: number;
  colCount: number;
  metrics: GridMetrics;
  columnHeader: (col: number) => string;
  rowHeader?: (row: number) => ReactNode;
  expanded: ReadonlySet<string>;
  onToggle: (slug: string) => void;
  onOpenLesson: (topic: GraphTopic, lesson: PathLesson) => void;
  highlight?: string | null;
};

/**
 * Topics as focusable nodes over static SVG prerequisite edges (ARCHITECTURE D27). Positions
 * come from `layoutGrid`; this component only draws them. A topic node is a button that shows
 * or hides its lessons as child rows; a lesson row opens the lesson drawer. The page scrolls
 * normally, and a graph wider than the page scrolls inside its own box.
 */
export function TopicGraph({ label, topics, rowCount, colCount, metrics, columnHeader, rowHeader, expanded, onToggle, onOpenLesson, highlight }: Props) {
  const t = useTranslations("roadmap");
  const markerId = `arrow-${useId().replace(/:/g, "")}`;
  const [pointed, setPointed] = useState<string | null>(null);

  // Render order is the reading and tab order: row by row, column by column.
  const ordered = useMemo(() => [...topics].sort((a, b) => a.row - b.row || a.col - b.col), [topics]);
  const layout = useMemo(
    () => layoutGrid(ordered.map((tp) => ({ id: tp.slug, row: tp.row, col: tp.col, children: expanded.has(tp.slug) ? tp.lessons.length : 0 })), rowCount, colCount, metrics),
    [ordered, expanded, rowCount, colCount, metrics],
  );
  const edges = ordered.flatMap((tp) => tp.requires.filter((r) => layout.items.has(r)).map((from) => ({ from, to: tp.slug })));
  const lit = new Set(pointed ? (topics.find((tp) => tp.slug === pointed)?.requires ?? []) : []);

  return (
    <div className="panel overflow-x-auto bg-paper-3">
      <div role="group" aria-label={label} className="relative m-4" style={{ width: layout.width, height: layout.height }}>
        <svg width={layout.width} height={layout.height} className="absolute inset-0 overflow-visible" aria-hidden>
          <defs>
            {(["rule-2", "accent"] as const).map((tone) => (
              <marker key={tone} id={`${markerId}-${tone}`} viewBox="0 0 8 8" refX="7" refY="4" markerWidth="7" markerHeight="7" orient="auto">
                <path d="M 0 0 L 8 4 L 0 8 z" fill={`var(--color-${tone})`} />
              </marker>
            ))}
          </defs>
          {layout.rows.slice(1).map((row) => (
            <line key={row.y} x1={0} x2={layout.width} y1={row.y - metrics.rowGap / 2} y2={row.y - metrics.rowGap / 2} stroke="var(--color-rule)" strokeWidth={1} />
          ))}
          {edges.map(({ from, to }) => {
            const active = pointed === to;
            return (
              <path
                key={`${from}-${to}`}
                d={gridEdgePath(layout.items.get(from)!, layout.items.get(to)!, layout)}
                fill="none"
                stroke={active ? "var(--color-accent)" : "var(--color-rule-2)"}
                strokeWidth={active ? 2.25 : 1.25}
                markerEnd={`url(#${markerId}-${active ? "accent" : "rule-2"})`}
              />
            );
          })}
        </svg>

        {layout.columns.map((column, col) => (
          <p key={col} aria-hidden className="measured absolute text-sm text-ink" style={{ left: column.x, top: 0, width: metrics.colWidth }}>
            {columnHeader(col)}
          </p>
        ))}
        {rowHeader
          ? layout.rows.map((row, r) => (
              <div key={r} className="absolute" style={{ left: 0, top: row.y, width: metrics.left - 24, height: row.height }}>
                {rowHeader(r)}
              </div>
            ))
          : null}

        <ol>
          {ordered.map((topic) => {
            const box = layout.items.get(topic.slug)!;
            const open = expanded.has(topic.slug);
            return (
              <TopicNode
                key={topic.slug}
                topic={topic}
                box={box}
                open={open}
                childGap={metrics.childGap}
                childHeight={metrics.childHeight}
                emphasis={lit.has(topic.slug) ? "prerequisite" : highlight === topic.slug ? "next" : null}
                onToggle={() => onToggle(topic.slug)}
                onPoint={(on) => setPointed(on ? topic.slug : null)}
                onOpenLesson={(lesson) => onOpenLesson(topic, lesson)}
                statusLabel={t(`status.${topic.status}`)}
                reviewLabel={t("reviewChip")}
              />
            );
          })}
        </ol>
      </div>
    </div>
  );
}

function TopicNode({
  topic,
  box,
  open,
  childGap,
  childHeight,
  emphasis,
  onToggle,
  onPoint,
  onOpenLesson,
  statusLabel,
  reviewLabel,
}: {
  topic: GraphTopic;
  box: { x: number; y: number; width: number; nodeHeight: number };
  open: boolean;
  childGap: number;
  childHeight: number;
  emphasis: "prerequisite" | "next" | null;
  onToggle: () => void;
  onPoint: (on: boolean) => void;
  onOpenLesson: (lesson: PathLesson) => void;
  statusLabel: string;
  reviewLabel: string;
}) {
  const t = useTranslations("roadmap");
  const listId = useId();
  const descriptionId = useId();
  return (
    <li className="absolute" style={{ left: box.x, top: box.y, width: box.width }}>
      <button
        type="button"
        aria-expanded={open}
        aria-controls={listId}
        aria-describedby={descriptionId}
        onClick={onToggle}
        onMouseEnter={() => onPoint(true)}
        onMouseLeave={() => onPoint(false)}
        onFocus={() => onPoint(true)}
        onBlur={() => onPoint(false)}
        className="block w-full rounded-card text-left"
        style={{ height: box.nodeHeight }}
      >
        <RoadmapNodeCard
          as="span"
          title={topic.title}
          status={topic.status}
          statusLabel={statusLabel}
          percent={topic.percent}
          review={topic.review ? { label: reviewLabel } : undefined}
          meta={topic.meta}
          className={cn(
            "h-full transition-[border-color,box-shadow] duration-[var(--dur-micro)] ease-out hover:border-accent",
            emphasis === "prerequisite" && "ring-2 ring-accent",
            emphasis === "next" && "ring-2 ring-accent ring-offset-2 ring-offset-paper-3",
          )}
        />
      </button>
      <span id={descriptionId} className="sr-only">
        {[topic.description, t("lessons", { count: topic.lessons.length })].filter(Boolean).join(". ")}
      </span>
      <ul id={listId} hidden={!open} aria-label={t("lessonsOf", { topic: topic.title })} className="ml-4 border-l border-rule-2 pl-2" style={{ marginTop: childGap }}>
        {topic.lessons.map((lesson) => (
          <li key={lesson.slug} style={{ height: childHeight }}>
            <LessonButton lesson={lesson} onOpen={() => onOpenLesson(lesson)} />
          </li>
        ))}
      </ul>
    </li>
  );
}

function LessonButton({ lesson, onOpen }: { lesson: PathLesson; onOpen: () => void }) {
  const t = useTranslations("roadmap");
  const Icon = lesson.status === "completed" ? Check : lesson.status === "started" ? CircleDot : Circle;
  return (
    <button
      type="button"
      onClick={onOpen}
      aria-haspopup="dialog"
      className="flex size-full items-center gap-2 rounded-control px-2 text-left text-sm text-ink transition-[background-color] duration-[var(--dur-micro)] hover:bg-paper-2"
    >
      <Icon aria-hidden className={cn("size-4 shrink-0", lesson.status === "completed" ? "text-accent" : "text-muted")} />
      <span className="min-w-0 flex-1 truncate">{lesson.title}</span>
      <span className="sr-only">
        {t(`lessonStatus.${lesson.status}`)}
        {lesson.is_required ? "" : `, ${t("optional")}`}
      </span>
      <Duration minutes={lesson.estimated_minutes} className="shrink-0 text-muted" />
    </button>
  );
}

/**
 * Which topics show their lessons. Kept by the page, above the graph-or-outline switch, so
 * crossing the width breakpoint does not reset it.
 */
export function useExpandedTopics(initial: string[] = []) {
  const [expanded, setExpanded] = useState<ReadonlySet<string>>(() => new Set(initial));
  const toggle = useCallback(
    (slug: string) =>
      setExpanded((current) => {
        const next = new Set(current);
        if (next.has(slug)) next.delete(slug);
        else next.add(slug);
        return next;
      }),
    [],
  );
  return { expanded, toggle, set: (slugs: string[]) => setExpanded(new Set(slugs)) };
}
