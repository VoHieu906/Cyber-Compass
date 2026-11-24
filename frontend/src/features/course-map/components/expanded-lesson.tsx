"use client";

import { ArrowRight, BookOpen, X } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useMemo } from "react";
import type { Box, Rect } from "@/lib/graph/shapes/geometry";
import { architectureSchema } from "@/lib/graph/shapes/schemas";
import { cn } from "@/lib/utils";
import { EXPANDED } from "../layout";
import type { MapLesson } from "../types";
import { ArchitectureView } from "./architecture-view";

/**
 * The inside of the focused lesson's node (F3 focus mode, D59): its actions beside the title card,
 * its parts in the lesson's shape below it, and what the chosen part does. Loaded on first use, so the course page ships only the overview. The architecture is
 * parsed with the zod mirror first; a malformed one shows a notice instead of breaking the page.
 */
export default function ExpandedLesson({
  lesson,
  titles,
  box,
  width,
  card,
  headingId,
  onClose,
  onFocusLesson,
  onParts,
  onHotPart,
  selected,
  onSelect,
  detail,
}: {
  lesson: MapLesson;
  titles: Map<string, string>;
  box: Box;
  /** The expanded node's width. */
  width: number;
  card: { width: number; height: number };
  headingId: string;
  onClose: () => void;
  onFocusLesson: (slug: string) => void;
  /** Part rectangles relative to the expanded node, for the edges to linked lessons. */
  onParts: (parts: Map<string, Rect>) => void;
  /** The part under the pointer or keyboard focus. */
  onHotPart: (id: string | null) => void;
  /** The chosen part: the map sizes the detail strip for it (detailHeight). */
  selected: string | null;
  onSelect: (id: string) => void;
  /** The detail strip's height. */
  detail: number;
}) {
  const t = useTranslations("courseMap");
  const parsed = useMemo(
    () =>
      lesson.shape
        ? architectureSchema.safeParse({
            version: 1,
            problem: lesson.problem ?? lesson.title,
            shape: lesson.shape,
            nodes: lesson.nodes,
            edges: lesson.edges,
            ...(lesson.rows ? { rows: lesson.rows } : {}),
            ...(lesson.cols ? { cols: lesson.cols } : {}),
          })
        : null,
    [lesson],
  );
  // One object per architecture: ArchitectureView reports its layout whenever this changes.
  const architecture = useMemo(
    () => (parsed?.success && parsed.data.shape ? { ...parsed.data, shape: parsed.data.shape } : null),
    [parsed],
  );
  const part = lesson.nodes.find((n) => n.id === selected);
  const areaLeft = (width - box.width) / 2;
  const areaTop = EXPANDED.header + EXPANDED.gap;

  return (
    <>
      {/* Beside the title card, centred on it: only the two actions (the shape is on the card's glyph and in its description). */}
      <div
        className="absolute top-0 right-0 flex items-center justify-end px-3"
        style={{ left: card.width + 12, height: card.height }}
      >
        <h3 id={headingId} className="sr-only">
          {t("parts", { title: lesson.title })}
        </h3>
        {/* Icon-only actions: the name is the aria-label, and a small tooltip shows it on hover and keyboard focus. */}
        <div className="flex justify-end gap-2">
          <Link
            href={`/learn/${lesson.slug}`}
            aria-label={t("open")}
            className={cn(ACTION, "bg-accent text-accent-ink hover:opacity-90")}
          >
            <BookOpen aria-hidden className="size-5" />
            <span aria-hidden className={ACTION_TIP}>
              {t("open")}
            </span>
          </Link>
          <button
            type="button"
            onClick={onClose}
            aria-label={t("back")}
            className={cn(ACTION, "border border-rule-2 bg-paper-2 text-ink hover:bg-paper-3")}
          >
            <X aria-hidden className="size-5" />
            <span aria-hidden className={ACTION_TIP}>
              {t("back")}
            </span>
          </button>
        </div>
      </div>

      <div className="absolute" style={{ left: areaLeft, top: areaTop, width: box.width, height: box.height }}>
        {architecture ? (
          <ArchitectureView
            architecture={architecture}
            box={box}
            selected={selected}
            onSelect={onSelect}
            label={t("parts", { title: lesson.title })}
            onHot={onHotPart}
            onLayout={(rects) =>
              onParts(new Map([...rects].map(([id, r]) => [id, { ...r, x: r.x + areaLeft, y: r.y + areaTop }])))
            }
          />
        ) : parsed ? (
          <p className="text-sm text-muted">{t("unavailable")}</p>
        ) : (
          <p className="flex h-full items-center justify-center px-6 text-center text-ink-2">{lesson.problem}</p>
        )}
      </div>

      <div
        aria-live="polite"
        className={cn("absolute right-3 bottom-3 left-3 text-sm", detail > EXPANDED.pad && "border-t border-rule pt-2")}
        style={{ height: Math.max(0, detail - 16) }}
      >
        {part ? (
          <div className="flex flex-col gap-1.5">
            <p className="line-clamp-2">
              <span className="font-medium text-ink">{part.label}</span>
              {part.note ? <span className="text-ink-2">: {part.note}</span> : null}
            </p>
            <div className="flex flex-wrap gap-x-4 gap-y-1">
              {part.section ? (
                <Link
                  href={`/learn/${lesson.slug}#${part.section}`}
                  className="inline-flex items-center gap-1 text-accent underline underline-offset-4"
                >
                  {t("readSection")}
                  <ArrowRight aria-hidden className="size-3.5" />
                </Link>
              ) : null}
              {part.lesson && titles.has(part.lesson) ? (
                <button
                  type="button"
                  onClick={() => onFocusLesson(part.lesson!)}
                  className="inline-flex items-center gap-1 text-accent underline underline-offset-4"
                >
                  {t("goToLesson", { title: titles.get(part.lesson)! })}
                  <ArrowRight aria-hidden className="size-3.5" />
                </button>
              ) : null}
            </div>
          </div>
        ) : lesson.shape && lesson.problem ? (
          // Until a part is chosen: the lesson's problem (the node no longer shows it, and touch has no hover).
          <p className="line-clamp-2">
            <span className="font-medium text-ink">{t("problemLabel")}</span>{" "}
            <span className="text-ink-2">{lesson.problem}</span>
          </p>
        ) : null}
      </div>
    </>
  );
}

/** A 40 by 40 icon action; the global focus ring applies. */
const ACTION = "group relative inline-flex size-10 items-center justify-center rounded-control";
/** Its name under it, after a short pause on hover, at once on keyboard focus; right-aligned so it stays in the panel. */
const ACTION_TIP =
  "pointer-events-none absolute top-full right-0 z-20 mt-1.5 rounded-control border border-rule-2 bg-paper-2 px-2 py-1 text-xs font-normal whitespace-nowrap text-ink opacity-0 shadow-[var(--shadow-overlay)] transition-opacity group-hover:opacity-100 group-hover:delay-300 group-focus-visible:opacity-100 motion-reduce:transition-none";
