"use client";

import { Target } from "lucide-react";
import { useTranslations } from "next-intl";
import dynamic from "next/dynamic";
import { useCallback, useEffect, useId, useMemo, useRef, useState, type KeyboardEvent, type MouseEvent } from "react";
import { statusIcon } from "@/features/catalog/components/curriculum";
import { useCourseProgress } from "@/features/catalog/hooks";
import { goTo, useCompassPageItems } from "@/features/compass/page-items";
import { SHAPE_BOX, fitBox } from "@/lib/graph/shapes/sizes";
import { pressed } from "@/lib/shortcuts/registry";
import { cn } from "@/lib/utils";
import { GOAL, layoutCourseMap, moveFrom, nodeState, type NodeState } from "../layout";
import type { CourseMapData, MapEdge } from "../types";
import { EdgeLegend, EDGE_STYLE, EdgeMarkers } from "./edge-style";
import { ShapeGlyph } from "./shape-glyph";

const FocusPanel = dynamic(() => import("./focus-panel"), {
  ssr: false,
  loading: () => <div className="h-full rounded-card border border-accent bg-paper-2" />,
});

/** The panel around a shape: its header, the shape's box and the detail strip below it. */
const panelFor = (box: { width: number; height: number }) => ({ width: box.width + 26, height: box.height + 200 });

/**
 * The course map (F3, D57 to D59): lessons as real buttons over static SVG relations, the goal
 * they all contribute to, and a focus mode where the chosen lesson expands into its architecture
 * while everything below moves out of the way. One tab stop; arrow keys follow the relations
 * (lib/shortcuts `courseMap.*`), Enter opens, Escape returns focus to the lesson it opened.
 * The overview renders on the server; focus mode loads on first use.
 */
export function CourseMap({
  map,
  courseSlug,
  courseTitle,
}: {
  map: CourseMapData;
  courseSlug: string;
  courseTitle: string;
}) {
  const t = useTranslations("courseMap");
  const tc = useTranslations("course");
  const { data: progress } = useCourseProgress(courseSlug);
  const [focused, setFocused] = useState<string | null>(null);
  const [active, setActive] = useState<string>(map.lessons[0]?.slug ?? GOAL);
  const [compact, setCompact] = useState(false);
  const wrapper = useRef<HTMLDivElement>(null);
  const nodes = useRef(new Map<string, HTMLElement>());
  const panelHeading = useId();

  useEffect(() => {
    const el = wrapper.current;
    if (!el || typeof ResizeObserver === "undefined") return;
    const observer = new ResizeObserver(([entry]) => setCompact(entry.contentRect.width < SHAPE_BOX.wide.width + 60));
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const footprint = compact ? SHAPE_BOX.compact : SHAPE_BOX.wide;
  const focusedLesson = map.lessons.find((l) => l.slug === focused);
  const box = useMemo(
    () =>
      fitBox(
        focusedLesson?.shape ?? null,
        focusedLesson?.nodes.length ?? 0,
        focusedLesson?.rows?.length ?? 0,
        footprint,
      ),
    [focusedLesson, footprint],
  );
  const layout = useMemo(() => layoutCourseMap(map, focused, panelFor(box)), [map, focused, box]);
  const titles = useMemo(() => new Map(map.lessons.map((l) => [l.slug, l.title])), [map]);

  useCompassPageItems([
    { id: "course-map", label: t("title"), run: () => goTo(document.getElementById("course-map-title")) },
  ]);

  const moveTo = useCallback((slug: string) => {
    setActive(slug);
    nodes.current.get(slug)?.focus();
  }, []);

  const open = (slug: string) => {
    if (slug === GOAL) return;
    setFocused(slug);
    setActive(slug);
  };

  const close = useCallback(() => {
    setFocused((current) => {
      if (current) requestAnimationFrame(() => nodes.current.get(current)?.focus());
      return null;
    });
  }, []);

  const onNodeKey = (event: KeyboardEvent<HTMLElement>, slug: string) => {
    for (const [id, key] of [
      ["courseMap.forward", "ArrowRight"],
      ["courseMap.backward", "ArrowLeft"],
      ["courseMap.next", "ArrowDown"],
      ["courseMap.previous", "ArrowUp"],
    ] as const) {
      if (pressed(event, id)) {
        event.preventDefault();
        return moveTo(moveFrom(map, slug, key));
      }
    }
  };

  const onMapKey = (event: KeyboardEvent<HTMLDivElement>) => {
    if (focused && pressed(event, "courseMap.close")) {
      event.preventDefault();
      close();
    }
  };

  // A click on the empty canvas (not on a lesson or the panel) returns to the overview.
  const onCanvasClick = (event: MouseEvent<HTMLDivElement>) => {
    if (focused && (event.target === event.currentTarget || event.target instanceof SVGElement)) close();
  };

  const edgeState = (e: MapEdge): "emphasis" | "dim" | "normal" =>
    !focused ? "normal" : e.from === focused || e.to === focused ? "emphasis" : "dim";

  return (
    <div className="flex flex-col gap-4" onKeyDown={onMapKey}>
      <p className="sr-only" aria-live="polite">
        {focusedLesson ? t("focused", { title: focusedLesson.title }) : ""}
      </p>
      <div ref={wrapper} className="overflow-x-auto rounded-card border border-rule bg-paper-3 p-4">
        <div
          role="group"
          aria-label={t("label", { course: courseTitle })}
          onClick={onCanvasClick}
          className="relative mx-auto transition-[height] duration-[var(--dur-long)] motion-reduce:transition-none"
          style={{ width: layout.width, height: layout.height }}
        >
          <svg width={layout.width} height={layout.height} className="absolute inset-0 overflow-visible" aria-hidden>
            <EdgeMarkers />
            {layout.bands.map((b) => (
              <g key={b.label}>
                <rect
                  x={0}
                  y={b.y - 8}
                  width={layout.width}
                  height={b.height + 16}
                  rx={8}
                  style={{ fill: "var(--color-paper)", stroke: "var(--color-rule)" }}
                  className="transition-[y,height] duration-[var(--dur-long)] motion-reduce:transition-none"
                />
                <text x={12} y={b.y + 14} fontSize={12} style={{ fill: "var(--color-ink-2)", fontWeight: 600 }}>
                  {b.label}
                </text>
              </g>
            ))}
            {layout.contributes.map((c) => (
              <path
                key={c.from}
                d={c.d}
                fill="none"
                {...EDGE_STYLE.contributes}
                className={cn(
                  "[transition:d_var(--dur-long),opacity_var(--dur-long)] motion-reduce:transition-none",
                  focused && "opacity-20",
                )}
              />
            ))}
            {layout.edges.map((e) => {
              const state = edgeState(e);
              const label = state === "emphasis" ? (e.label ?? t(`edges.${e.type}`)) : e.label;
              return (
                <g
                  key={`${e.from}>${e.to}>${e.type}`}
                  className={cn(
                    "[transition:opacity_var(--dur-long)] motion-reduce:transition-none",
                    state === "dim" && "opacity-20",
                  )}
                >
                  {e.type === "parallel-to" ? (
                    <path
                      d={e.d}
                      fill="none"
                      strokeWidth={state === "emphasis" ? 6 : 5}
                      style={{ stroke: state === "emphasis" ? "var(--color-accent)" : "var(--color-rule-2)" }}
                      className="[transition:d_var(--dur-long)] motion-reduce:transition-none"
                    />
                  ) : null}
                  <path
                    d={e.d}
                    fill="none"
                    {...EDGE_STYLE[e.type]}
                    {...(e.type === "parallel-to"
                      ? { style: { stroke: "var(--color-paper-3)" } }
                      : state === "emphasis"
                        ? {
                            style: { stroke: "var(--color-accent)" },
                            strokeWidth: Number(EDGE_STYLE[e.type].strokeWidth) + 0.75,
                          }
                        : {})}
                    className="[transition:d_var(--dur-long)] motion-reduce:transition-none"
                  />
                  {label ? (
                    <text
                      x={e.labelAt.x}
                      y={e.labelAt.y - (e.labelAt.anchor === "middle" ? 9 : 0)}
                      textAnchor={e.labelAt.anchor}
                      dominantBaseline="central"
                      fontSize={11}
                      style={{
                        fill: state === "emphasis" ? "var(--color-ink)" : "var(--color-muted)",
                        paintOrder: "stroke",
                        stroke: "var(--color-paper-3)",
                        strokeWidth: 4,
                        fontFamily: "var(--font-measured)",
                      }}
                    >
                      {label}
                    </text>
                  ) : null}
                </g>
              );
            })}
            {focused && layout.focusPanel ? (
              <path
                d={`M ${layout.boxes.get(focused)!.x + layout.boxes.get(focused)!.width / 2} ${layout.boxes.get(focused)!.y + layout.boxes.get(focused)!.nodeHeight} V ${layout.focusPanel.y}`}
                style={{ stroke: "var(--color-accent)" }}
                strokeWidth={2}
              />
            ) : null}
          </svg>

          {map.lessons.map((lesson) => {
            const b = layout.boxes.get(lesson.slug)!;
            const state = nodeState(map, focused, lesson.slug);
            const status = progress?.lessons[lesson.slug];
            const Icon = status ? statusIcon[status] : null;
            const isFocused = state === "focused";
            return (
              <div key={lesson.slug} className="contents">
                <button
                  ref={(el) => void (el ? nodes.current.set(lesson.slug, el) : nodes.current.delete(lesson.slug))}
                  type="button"
                  tabIndex={active === lesson.slug ? 0 : -1}
                  aria-expanded={isFocused}
                  aria-describedby={`${lesson.slug}-map-desc`}
                  data-state={state}
                  onClick={() => (isFocused ? close() : open(lesson.slug))}
                  onFocus={() => setActive(lesson.slug)}
                  onKeyDown={(e) => onNodeKey(e, lesson.slug)}
                  className={cn(
                    "absolute top-0 left-0 flex flex-col items-start gap-1 rounded-card border p-3 text-left transition-[transform,border-color,background-color,opacity] duration-[var(--dur-long)] motion-reduce:transition-none",
                    nodeClass(state),
                  )}
                  style={{ width: b.width, height: b.nodeHeight, transform: `translate(${b.x}px, ${b.y}px)` }}
                >
                  <span className="flex w-full items-start gap-2">
                    {lesson.shape ? (
                      <ShapeGlyph
                        shape={lesson.shape}
                        className={cn(
                          "mt-0.5 transition-[filter,opacity]",
                          state === "dimmed" ? "opacity-40 blur-[0.5px]" : "text-accent",
                        )}
                      />
                    ) : null}
                    <span
                      className={cn(
                        "line-clamp-2 flex-1 leading-snug font-medium",
                        state === "dimmed" ? "text-muted" : "text-ink",
                      )}
                    >
                      {lesson.title}
                    </span>
                    {Icon && status ? (
                      <Icon
                        aria-hidden
                        className={cn("mt-0.5 size-4 shrink-0", status === "completed" ? "text-accent" : "text-muted")}
                      />
                    ) : null}
                  </span>
                  {lesson.problem ? (
                    <span
                      aria-hidden
                      className={cn(
                        "line-clamp-2 text-[13px] leading-[1.35] transition-[filter,opacity]",
                        state === "dimmed" ? "opacity-50 blur-[0.6px]" : "text-ink-2",
                      )}
                    >
                      {lesson.problem}
                    </span>
                  ) : null}
                </button>
                {/* Outside the button, so its name is exactly its visible title (WCAG 2.5.3). */}
                <span id={`${lesson.slug}-map-desc`} className="sr-only">
                  {[
                    lesson.problem,
                    lesson.shape ? t("shape", { shape: t(`shapes.${lesson.shape}`) }) : null,
                    status ? tc(`lessonStatus.${status}`) : null,
                  ]
                    .filter(Boolean)
                    .join(". ")}
                </span>
                {isFocused && layout.focusPanel ? (
                  <div
                    role="region"
                    aria-labelledby={panelHeading}
                    className="absolute"
                    style={{
                      left: layout.focusPanel.x,
                      top: layout.focusPanel.y,
                      width: layout.focusPanel.width,
                      height: layout.focusPanel.height,
                    }}
                  >
                    <FocusPanel
                      lesson={lesson}
                      titles={titles}
                      box={box}
                      headingId={panelHeading}
                      onClose={close}
                      onFocusLesson={(slug) => (
                        open(slug),
                        requestAnimationFrame(() => nodes.current.get(slug)?.focus())
                      )}
                    />
                  </div>
                ) : null}
              </div>
            );
          })}

          <div
            ref={(el) => void (el ? nodes.current.set(GOAL, el) : nodes.current.delete(GOAL))}
            role="group"
            aria-label={t("goal")}
            tabIndex={active === GOAL ? 0 : -1}
            onFocus={() => setActive(GOAL)}
            onKeyDown={(e) => onNodeKey(e, GOAL)}
            className={cn(
              "absolute top-0 left-0 flex flex-col gap-1.5 rounded-card border-2 border-ink-2 bg-paper-2 p-3 outline-offset-2 transition-[transform,opacity] duration-[var(--dur-long)] motion-reduce:transition-none",
              focused && "opacity-80",
            )}
            style={{
              width: layout.boxes.get(GOAL)!.width,
              height: layout.boxes.get(GOAL)!.nodeHeight,
              transform: `translate(${layout.boxes.get(GOAL)!.x}px, ${layout.boxes.get(GOAL)!.y}px)`,
            }}
          >
            <span className="flex items-center gap-1.5 text-sm font-medium text-ink-2">
              <Target aria-hidden className="size-4" />
              {t("goal")}
            </span>
            <span className="line-clamp-4 text-[14px] leading-snug text-ink">{map.problem}</span>
          </div>
        </div>
      </div>
      <p className="text-sm text-muted">{t("keysHint")}</p>
      <EdgeLegend
        types={[...new Set(map.edges.map((e) => e.type))]}
        shapes={[...new Set(map.lessons.flatMap((l) => (l.shape ? [l.shape] : [])))]}
      />
    </div>
  );
}

function nodeClass(state: NodeState): string {
  switch (state) {
    case "focused":
      return "border-accent bg-accent-soft ring-2 ring-accent/40";
    case "related":
      return "border-ink-2 bg-paper-2";
    case "dimmed":
      return "border-rule bg-paper saturate-0";
    default:
      return "border-rule-2 bg-paper-2 hover:border-ink-2";
  }
}
