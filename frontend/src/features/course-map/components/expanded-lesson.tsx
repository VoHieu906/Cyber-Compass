"use client";

import { ArrowRight, BookOpen, X } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useMemo, useState } from "react";
import type { Box, Rect } from "@/lib/graph/shapes/geometry";
import { architectureSchema } from "@/lib/graph/shapes/schemas";
import { EXPANDED } from "../layout";
import type { MapLesson } from "../types";
import { ArchitectureView } from "./architecture-view";

/**
 * The inside of the focused lesson's node (F3 focus mode, D59): its actions beside the title card,
 * its parts in the lesson's shape below it, each linked back to the card, and what the chosen part
 * does. Loaded on first use, so the course page ships only the overview. The architecture is
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
}) {
  const t = useTranslations("courseMap");
  const [selected, setSelected] = useState<string | null>(null);
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
      <div
        className="absolute top-0 right-0 flex flex-col items-end gap-2 p-3 text-right"
        style={{ left: card.width + 12 }}
      >
        <h3 id={headingId} className="sr-only">
          {t("parts", { title: lesson.title })}
        </h3>
        <p className="text-sm text-ink-2">
          {lesson.shape ? (
            <>
              <span className="font-medium text-ink">{t(`shapes.${lesson.shape}`)}</span>:{" "}
              {t(`shapeHelp.${lesson.shape}`)}
            </>
          ) : (
            t("noShape")
          )}
        </p>
        <div className="flex flex-wrap justify-end gap-2">
          <Link
            href={`/learn/${lesson.slug}`}
            className="inline-flex items-center gap-1.5 rounded-control bg-accent px-3 py-1.5 text-sm font-medium text-accent-ink hover:opacity-90"
          >
            <BookOpen aria-hidden className="size-4" />
            {t("open")}
          </Link>
          <button
            type="button"
            onClick={onClose}
            className="inline-flex items-center gap-1.5 rounded-control border border-rule-2 bg-paper-2 px-3 py-1.5 text-sm text-ink hover:bg-paper-3"
          >
            <X aria-hidden className="size-4" />
            {t("back")}
          </button>
        </div>
      </div>

      <div className="absolute" style={{ left: areaLeft, top: areaTop, width: box.width, height: box.height }}>
        {architecture ? (
          <ArchitectureView
            architecture={architecture}
            box={box}
            selected={selected}
            onSelect={setSelected}
            label={t("parts", { title: lesson.title })}
            // The title card's bottom centre, in the shape box's coordinates: every part links back to it.
            anchor={{ x: card.width / 2 - areaLeft, y: card.height - areaTop }}
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
        className="absolute right-3 bottom-3 left-3 border-t border-rule pt-2 text-sm"
        style={{ height: EXPANDED.detail - 16 }}
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
        ) : lesson.shape ? (
          <p className="text-muted">{t("pickPart")}</p>
        ) : null}
      </div>
    </>
  );
}
