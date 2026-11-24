"use client";

import { Target } from "lucide-react";
import { useTranslations } from "next-intl";
import dynamic from "next/dynamic";
import {
  useCallback,
  useEffect,
  useId,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
  type MouseEvent,
} from "react";
import { createPortal } from "react-dom";
import { statusIcon } from "@/features/catalog/components/curriculum";
import { useCourseProgress } from "@/features/catalog/hooks";
import { goTo, useCompassPageItems } from "@/features/compass/page-items";
import { SHAPE_BOX, fitBox } from "@/lib/graph/shapes/sizes";
import { wrapLabel } from "@/lib/graph/text";
import { pressed } from "@/lib/shortcuts/registry";
import { cn } from "@/lib/utils";
import type { Rect } from "@/lib/graph/shapes/geometry";
import { roundedPath } from "@/lib/graph/shapes/route";
import {
  GOAL,
  NARROW_NODE,
  NODE,
  expandedSize,
  layoutCourseMap,
  moveFrom,
  detailHeight,
  nodeState,
  routePartLinks,
  type NodeState,
} from "../layout";
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
  const [hotPart, setHotPart] = useState<string | null>(null);
  const [selectedPart, setSelectedPart] = useState<string | null>(null);
  // The problem tooltip: after a short pause on hover (no flicker while the pointer crosses the map), or on keyboard focus.
  // The same tooltip names a relation under the pointer (UI Part 2).
  const [tip, setTip] = useState<{ text: string; rect: DOMRect; kind: "problem" | "edge" } | null>(null);
  const tipTimer = useRef<number | undefined>(undefined);
  const later = (next: NonNullable<typeof tip>) => {
    window.clearTimeout(tipTimer.current);
    tipTimer.current = window.setTimeout(() => setTip(next), TIP_DELAY);
  };
  const showTip = (problem: string, el: HTMLElement) =>
    later({ text: problem, rect: el.getBoundingClientRect(), kind: "problem" });
  // Hover or keyboard focus on a lesson: its relations and the lessons at their other ends stay, the rest dims.
  const [hoverLesson, setHoverLesson] = useState<string | null>(null);
  const [keyLesson, setKeyLesson] = useState<string | null>(null);
  const [hotEdge, setHotEdge] = useState<string | null>(null);
  const hideTip = useCallback(() => {
    window.clearTimeout(tipTimer.current);
    setTip(null);
  }, []);
  // It is placed once, against the viewport: any scroll or resize closes it rather than leave it behind.
  useEffect(() => {
    if (!tip) return;
    window.addEventListener("scroll", hideTip, true);
    window.addEventListener("resize", hideTip);
    return () => {
      window.removeEventListener("scroll", hideTip, true);
      window.removeEventListener("resize", hideTip);
    };
  }, [tip, hideTip]);
  useEffect(() => () => window.clearTimeout(tipTimer.current), []);

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
  const narrow = useMemo(
    () => layoutCourseMap(map, null, NODE, false, { route: false }).width > boxWidth,
    [map, boxWidth],
  );
  const node = narrow ? NARROW_NODE : NODE;
  const overviewWidth = useMemo(
    () => layoutCourseMap(map, null, node, narrow, { route: false }).width,
    [map, node, narrow],
  );
  // The wide footprint when the map still fits its box with it, else the compact one (and the box scrolls sideways).
  const wide = overviewWidth + expandedSize(fit(SHAPE_BOX.wide), node).width - node.width <= boxWidth;
  const footprint = wide ? SHAPE_BOX.wide : SHAPE_BOX.compact;
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
  // The detail strip shows the chosen part, else the problem (lessons with a shape; without one the problem fills the node).
  const detail = detailHeight(
    focusedLesson?.shape ? focusedLesson.problem : null,
    !!selectedPart,
    expandedSize(box, node).width,
  );
  const layout = useMemo(
    () => layoutCourseMap(map, focused, expandedSize(box, node, detail), narrow),
    [map, focused, box, node, narrow, detail],
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
    el.scrollTo?.({
      left: Math.max(0, right - el.clientWidth > left ? left : right - el.clientWidth),
      behavior: reduce ? "auto" : "smooth",
    });
  }, [focused, layout]);
  const frame = focused ? layout.boxes.get(focused) : undefined;
  // Parts that lead to another lesson: an edge from the part, around the other parts, to that lesson's node.
  const partLinks = useMemo(() => {
    if (!frame || !focusedLesson || !parts.size) return [];
    const links = focusedLesson.nodes.flatMap((n) => {
      const to = n.lesson && n.lesson !== focusedLesson.slug ? layout.boxes.get(n.lesson) : undefined;
      if (!parts.has(n.id) || !to) return [];
      return [
        {
          id: n.id,
          key: n.lesson!,
          to: { x: to.x - frame.x, y: to.y - frame.y, width: to.width, height: to.nodeHeight },
        },
      ];
    });
    const inner = routePartLinks(frame, { width: layout.node.width, height: frame.nodeHeight }, parts, links, detail);
    // The rest, from the node's edge to the lesson, routed around every node (drawn beneath them, in map coordinates).
    const outer = layout.routeLinks(
      inner.map((l) => ({
        from: { ...l.exit, x: l.exit.x + frame.x, y: l.exit.y + frame.y },
        terminal: layout.partPorts.get(l.id)!,
      })),
    );
    return inner.map((l, i) => ({
      ...l,
      outer: roundedPath(outer[i].points, 8),
      fallback: l.fallback || outer[i].fallback,
    }));
  }, [frame, focusedLesson, parts, layout, detail]);
  const partLinkProps = (id: string) => ({
    fill: "none",
    strokeWidth: id === hotPart ? 2.25 : 1.5,
    strokeDasharray: "4 3",
    "data-state": hotPart === null ? undefined : id === hotPart ? "lit" : "dim",
    className: "[transition:opacity_var(--dur-micro)] motion-reduce:transition-none",
    style: { stroke: "var(--color-accent)", opacity: hotPart !== null && id !== hotPart ? 0.3 : 1 },
  });
  useEffect(() => {
    if (process.env.NODE_ENV !== "production")
      for (const l of partLinks.filter((l) => l.fallback))
        console.debug(`[course map] no clean route for part ${l.id}; drawn beneath the parts`);
  }, [partLinks]);
  const titles = useMemo(() => new Map(map.lessons.map((l) => [l.slug, l.title])), [map]);
  useEffect(() => {
    if (process.env.NODE_ENV !== "production")
      for (const f of layout.fallbacks) console.debug(`[course map] no clean route for ${f}; drawn beneath the nodes`);
  }, [layout]);

  useCompassPageItems([
    { id: "course-map", label: t("title"), run: () => goTo(document.getElementById("course-map-title")) },
  ]);

  const moveTo = useCallback((slug: string) => {
    setActive(slug);
    nodes.current.get(slug)?.focus();
  }, []);

  const open = (slug: string) => {
    if (slug === GOAL) return;
    hideTip();
    setParts(new Map());
    setHotPart(null);
    setSelectedPart(null);
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
    // Escape closes the tooltip first; the next Escape leaves focus mode.
    if (tip && event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      return hideTip();
    }
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

  const hot = (hoverLesson ?? keyLesson) !== focused ? (hoverLesson ?? keyLesson) : null;
  const near = useMemo(
    () =>
      hot
        ? new Set([
            hot,
            ...map.edges.flatMap((e) => (e.from === hot ? [e.to] : e.to === hot ? [e.from] : [])),
            ...(layout.contributes.some((c) => c.from === hot) ? [GOAL] : []),
          ])
        : null,
    [hot, map, layout],
  );
  const edgeState = (e: MapEdge): "emphasis" | "dim" | "normal" =>
    hot
      ? e.from === hot || e.to === hot
        ? "emphasis"
        : "dim"
      : !focused
        ? "normal"
        : e.from === focused || e.to === focused
          ? "emphasis"
          : "dim";
  const edgeName = (e: MapEdge) => `${titles.get(e.from)} → ${t(`edges.${e.type}`)} → ${titles.get(e.to)}`;

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
            {/* Routes are computed once per layout; a new focus fades its routes in rather than morphing them (none with reduced motion). */}
            <g
              key={focused ?? "overview"}
              className="transition-opacity duration-[var(--dur-long)] motion-reduce:transition-none starting:opacity-0"
            >
              {/* The rest of each part link, from the expanded node's edge to its lesson: beneath the lesson nodes. */}
              {frame ? (
                <g>
                  {partLinks.map((l) => (
                    <path
                      key={l.id}
                      d={l.outer}
                      data-link-out={l.id}
                      markerEnd="url(#cm-solid)"
                      {...partLinkProps(l.id)}
                    />
                  ))}
                </g>
              ) : null}
              {layout.contributes.map((c) => (
                <path
                  key={c.from}
                  d={c.d}
                  fill="none"
                  {...EDGE_STYLE.contributes}
                  className={cn(
                    "[transition:d_var(--dur-long),opacity_var(--dur-long)] motion-reduce:transition-none",
                    hot ? c.from !== hot && "opacity-30" : focused && "opacity-20",
                  )}
                />
              ))}
              {layout.edges.map((e) => {
                const key = `${e.from}>${e.to}>${e.type}`;
                const state = edgeState(e);
                // Focus mode words the focused lesson's relations; a hover leaves that to the relation's tooltip.
                const label = state === "emphasis" && !hot && !e.quiet ? (e.label ?? t(`edges.${e.type}`)) : e.label;
                const wide = hotEdge === key ? 1.25 : 0;
                return (
                  <g
                    key={key}
                    data-edge={key}
                    data-state={state}
                    className={cn(
                      "[transition:opacity_var(--dur-micro)] motion-reduce:transition-none",
                      state === "dim" && (hot ? "opacity-30" : "opacity-20"),
                    )}
                  >
                    {e.type === "parallel-to" ? (
                      <path
                        d={e.d}
                        fill="none"
                        strokeWidth={(state === "emphasis" ? 6 : 5) + wide}
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
                        : state === "emphasis" || wide
                          ? {
                              style: { stroke: "var(--color-accent)" },
                              strokeWidth: Number(EDGE_STYLE[e.type].strokeWidth) + 0.75 + wide,
                            }
                          : {})}
                      className="[transition:d_var(--dur-long)] motion-reduce:transition-none"
                    />
                    {/* A wide invisible stroke takes the pointer: the relation's name after a pause, and a thicker line. */}
                    <path
                      d={e.d}
                      fill="none"
                      stroke="transparent"
                      strokeWidth={12}
                      pointerEvents="stroke"
                      data-edge-hit={key}
                      onPointerEnter={(ev) => {
                        setHotEdge(key);
                        later({
                          text: edgeName(e),
                          rect: new DOMRect(ev.clientX, ev.clientY - 6, 0, 12),
                          kind: "edge",
                        });
                      }}
                      onPointerLeave={() => {
                        setHotEdge(null);
                        hideTip();
                      }}
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
            </g>
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
                  onFocus={(e) => {
                    setActive(lesson.slug);
                    // Keyboard focus only: a click opens the lesson instead.
                    if (!e.currentTarget.matches(":focus-visible")) return;
                    setKeyLesson(lesson.slug);
                    if (!isFocused && lesson.problem) showTip(lesson.problem, e.currentTarget);
                  }}
                  onBlur={() => {
                    hideTip();
                    setKeyLesson(null);
                  }}
                  onPointerEnter={(e) => {
                    if (e.pointerType !== "mouse") return;
                    setHoverLesson(lesson.slug);
                    if (!isFocused && lesson.problem) showTip(lesson.problem, e.currentTarget);
                  }}
                  onPointerLeave={() => {
                    hideTip();
                    setHoverLesson(null);
                  }}
                  data-hot={hot && !isFocused ? (near!.has(lesson.slug) ? "lit" : "dim") : undefined}
                  onKeyDown={(e) => onNodeKey(e, lesson.slug)}
                  className={cn(
                    "absolute top-0 left-0 flex flex-col items-start justify-center gap-1 rounded-card border p-3 text-left transition-[transform,border-color,background-color,opacity] duration-[var(--dur-long)] motion-reduce:transition-none",
                    isFocused && "z-10",
                    nodeClass(state),
                    // Faded content on an opaque card, so the lines beneath never show through; the focused lesson never fades.
                    hot && !isFocused && !near!.has(lesson.slug) && "[&>*]:opacity-30",
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
                    {/* Under the parts and over the node's own background: a part's link, from the part to the node's edge. */}
                    <svg
                      className="pointer-events-none absolute inset-0 overflow-visible"
                      width={b.width}
                      height={b.height}
                      aria-hidden
                    >
                      {partLinks.map((l) => (
                        <path key={l.id} d={l.inner} data-link={l.id} {...partLinkProps(l.id)} />
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
                      onHotPart={setHotPart}
                      selected={selectedPart}
                      onSelect={setSelectedPart}
                      detail={detail}
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
            <span className={cn("text-[14px] leading-snug text-ink", narrow ? "line-clamp-2" : "line-clamp-4")}>
              {map.problem}
            </span>
          </div>
        </div>
      </div>
      {tip ? (
        <MapTip
          rect={tip.rect}
          text={tip.text}
          testId={tip.kind === "edge" ? "course-map-edge-tip" : "course-map-tip"}
        />
      ) : null}
      <p className="text-sm text-muted">{t("keysHint")}</p>
      <EdgeLegend
        types={[...new Set(map.edges.map((e) => e.type))]}
        shapes={[...new Set(map.lessons.flatMap((l) => (l.shape ? [l.shape] : [])))]}
      />
    </div>
  );
}

const TIP_DELAY = 300;

/**
 * A lesson's problem beside its node (UI Part 1). Visual only (aria-hidden): screen readers get the
 * problem from the node's description. In a portal, so the map's scroll box never clips it, and
 * kept inside the viewport: above the node, below when there is no room, nudged in from the sides.
 * The problem only: the title is on the node and in its name. A relation's tooltip uses it too, at the pointer.
 */
function MapTip({ rect, text, testId }: { rect: DOMRect; text: string; testId: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const [at, setAt] = useState<{ left: number; top: number } | null>(null);
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    const margin = 8;
    const w = el.offsetWidth;
    const h = el.offsetHeight;
    const left = Math.min(Math.max(margin, rect.left + rect.width / 2 - w / 2), window.innerWidth - w - margin);
    const above = rect.top - h - margin;
    setAt({ left, top: above >= margin ? above : Math.min(rect.bottom + margin, window.innerHeight - h - margin) });
  }, [rect]);
  return createPortal(
    <div
      ref={ref}
      aria-hidden
      data-testid={testId}
      className="pointer-events-none fixed z-50 max-w-[20rem] rounded-control border border-rule-2 bg-paper-2 px-3 py-2 text-sm text-ink shadow-[var(--shadow-overlay)]"
      style={at ?? { left: 0, top: 0, visibility: "hidden" }}
    >
      {text}
    </div>,
    document.body,
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
