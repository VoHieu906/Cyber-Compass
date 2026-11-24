"use client";

import { Target } from "lucide-react";
import { useTranslations } from "next-intl";
import dynamic from "next/dynamic";
import { useCallback, useEffect, useId, useMemo, useRef, useState, type KeyboardEvent, type MouseEvent } from "react";
import { statusIcon } from "@/features/catalog/components/curriculum";
import { useCourseProgress } from "@/features/catalog/hooks";
import { goTo, useCompassPageItems } from "@/features/compass/page-items";
import { SHAPE_BOX, fitBox } from "@/lib/graph/shapes/sizes";
import { wrapLabel } from "@/lib/graph/text";
import { pressed } from "@/lib/shortcuts/registry";
import { cn } from "@/lib/utils";
import { borderPoint, centre, type Rect } from "@/lib/graph/shapes/geometry";
import { GOAL, NARROW_NODE, NODE, expandedSize, layoutCourseMap, moveFrom, nodeState, type NodeState } from "../layout";
import type { CourseMapData, MapEdge } from "../types";
import { EdgeLegend, EDGE_STYLE, EdgeMarkers } from "./edge-style";
import { ShapeGlyph } from "./shape-glyph";

/** The map's inner width at a 1280 window: the 1440 container minus gutters, scrollbar and the box padding. */
const BOX_AT_1280 = 1104;

const ExpandedLesson = dynamic(() => import("./expanded-lesson"), { ssr: false });


/**
 * The course map (F3, D57 to D59): lessons as real buttons over static SVG relations, the goal
 * they all contribute to, and a focus mode where the chosen lesson's own node grows in place into
 * its architecture while the other nodes reflow around it (no zoom, no panel). One tab stop; arrow keys follow the relations
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
  // Until measured (and on the server): the box at a 1280 window, so the common laptop never reflows on load.
  const [boxWidth, setBoxWidth] = useState(BOX_AT_1280);
  const wrapper = useRef<HTMLDivElement>(null);
  const nodes = useRef(new Map<string, HTMLElement>());
  const expandedHeading = useId();
  const [parts, setParts] = useState<Map<string, Rect>>(new Map());

  useEffect(() => {
    const el = wrapper.current;
    if (!el || typeof ResizeObserver === "undefined") return;
    const observer = new ResizeObserver(([entry]) => setBoxWidth(entry.contentRect.width));
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const focusedLesson = map.lessons.find((l) => l.slug === focused);
  const fit = (footprint: { width: number; height: number }) =>
    fitBox(focusedLesson?.shape ?? null, focusedLesson?.nodes.length ?? 0, focusedLesson?.rows?.length ?? 0, footprint);
  // D61: narrower nodes and a goal band when the overview would not fit the box at the D59 sizes.
  const narrow = useMemo(() => layoutCourseMap(map, null, NODE).width > boxWidth, [map, boxWidth]);
  const node = narrow ? NARROW_NODE : NODE;
  const overviewWidth = useMemo(() => layoutCourseMap(map, null, node, narrow).width, [map, node, narrow]);
  // The wide footprint when the map still fits its box with it, else the compact one (and the box scrolls sideways).
  const wide = overviewWidth + expandedSize(fit(SHAPE_BOX.wide), node).width - node.width <= boxWidth;
  const footprint = wide ? SHAPE_BOX.wide : SHAPE_BOX.compact;
  const box = useMemo(
    () => fitBox(focusedLesson?.shape ?? null, focusedLesson?.nodes.length ?? 0, focusedLesson?.rows?.length ?? 0, footprint),
    [focusedLesson, footprint],
  );
  const layout = useMemo(
    () => layoutCourseMap(map, focused, expandedSize(box, node), narrow),
    [map, focused, box, node, narrow],
  );

  // Bring the expanded node into view inside the map's own box: only its scrollLeft moves, never the page.
  useEffect(() => {
    const el = wrapper.current;
    const node = focused ? layout.boxes.get(focused) : undefined;
    if (!el || !node) return;
    const canvas = el.firstElementChild as HTMLElement | null;
    const offset = canvas ? canvas.offsetLeft - el.offsetLeft : 0;
    const left = offset + node.x - 16;
    const right = offset + node.x + node.width + 16;
    if (left >= el.scrollLeft && right <= el.scrollLeft + el.clientWidth) return;
    const reduce = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
    el.scrollTo?.({ left: Math.max(0, right - el.clientWidth > left ? left : right - el.clientWidth), behavior: reduce ? "auto" : "smooth" });
  }, [focused, layout]);
  const frame = focused ? layout.boxes.get(focused) : undefined;
  // Parts that lead to another lesson: an edge from the part to that lesson's node.
  const partLinks = useMemo(
    () =>
      frame && focusedLesson
        ? focusedLesson.nodes.flatMap((n) => {
            const r = parts.get(n.id);
            const to = n.lesson ? layout.boxes.get(n.lesson) : undefined;
            if (!r || !to) return [];
            const from = { ...r, x: r.x + frame.x, y: r.y + frame.y };
            const target = { x: to.x, y: to.y, width: to.width, height: to.nodeHeight };
            const a = borderPoint(from, centre(target));
            const b = borderPoint(target, centre(from));
            return [{ id: n.id, d: `M ${a.x} ${a.y} L ${b.x} ${b.y}` }];
          })
        : [],
    [frame, focusedLesson, parts, layout],
  );
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
    setParts(new Map());
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
      <div ref={wrapper} className="overflow-x-auto rounded-control bg-paper-3 p-4">
        <div
          role="group"
          aria-label={t("label", { course: courseTitle })}
          onClick={onCanvasClick}
          className="relative mx-auto"
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
                />
                {/* Wrapped to the lane header, so a long chapter name never runs under the first column. */}
                <text x={12} y={b.y + 14} fontSize={12} style={{ fill: "var(--color-ink-2)", fontWeight: 600 }}>
                  {wrapLabel(b.label, 12, 3).map((line, i) => (
                    <tspan key={i} x={12} dy={i ? 16 : 0}>
                      {line}
                    </tspan>
                  ))}
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
                    isFocused && "z-10",
                    nodeClass(state),
                  )}
                  style={{ width: layout.node.width, height: b.nodeHeight, transform: `translate(${b.x}px, ${b.y}px)` }}
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
                        "line-clamp-3 flex-1 leading-snug font-medium",
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
                {isFocused ? (
                  // The node itself, grown in place; its title card is the button above, painted on top.
                  <div
                    role="region"
                    aria-labelledby={expandedHeading}
                    className="absolute rounded-card border-2 border-accent bg-paper-2 transition-[opacity] duration-[var(--dur-long)] motion-reduce:transition-none starting:opacity-0"
                    style={{ left: b.x, top: b.y, width: b.width, height: b.height }}
                  >
                    {/* Under the parts (they are opaque) and over the node's own background: a part's edge to the lesson it leads to. */}
                    <svg className="pointer-events-none absolute inset-0 overflow-visible" width={b.width} height={b.height} aria-hidden>
                      {partLinks.map((l) => (
                        <path
                          key={l.id}
                          d={l.d}
                          transform={`translate(${-b.x} ${-b.y})`}
                          fill="none"
                          strokeWidth={1.5}
                          strokeDasharray="4 3"
                          markerEnd="url(#cm-solid)"
                          style={{ stroke: "var(--color-accent)" }}
                        />
                      ))}
                    </svg>
                    <ExpandedLesson
                      lesson={lesson}
                      titles={titles}
                      box={box}
                      width={b.width}
                      card={{ width: layout.node.width, height: b.nodeHeight }}
                      headingId={expandedHeading}
                      onClose={close}
                      onParts={setParts}
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
            <span className={cn("text-[14px] leading-snug text-ink", narrow ? "line-clamp-2" : "line-clamp-4")}>{map.problem}</span>
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
