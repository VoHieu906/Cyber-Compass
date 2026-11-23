"use client";

import { ArrowRight, BookOpen, X } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useMemo, useState } from "react";
import type { Box } from "@/lib/graph/shapes/geometry";
import { architectureSchema } from "@/lib/graph/shapes/schemas";
import type { MapLesson } from "../types";
import { ArchitectureView } from "./architecture-view";
import { ShapeGlyph } from "./shape-glyph";

/**
 * The focused lesson expanded into its architecture (F3 focus mode). Loaded on first use, so the
 * course page ships only the overview. The architecture is parsed with the zod mirror before it
 * is drawn; a malformed one shows a notice instead of breaking the page.
 */
export default function FocusPanel({
  lesson,
  titles,
  box,
  headingId,
  onClose,
  onFocusLesson,
}: {
  lesson: MapLesson;
  titles: Map<string, string>;
  box: Box;
  headingId: string;
  onClose: () => void;
  onFocusLesson: (slug: string) => void;
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
  const part = lesson.nodes.find((n) => n.id === selected);

  return (
    <div className="flex h-full flex-col gap-3 rounded-card border border-accent bg-paper-2 p-3 shadow-[0_12px_32px_-16px_oklch(0%_0_0/0.35)] transition-[opacity,translate] duration-[var(--dur-long)] motion-reduce:transition-none starting:translate-y-[-6px] starting:opacity-0">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 items-start gap-2">
          {lesson.shape ? <ShapeGlyph shape={lesson.shape} className="mt-0.5 text-accent" /> : null}
          <div className="min-w-0">
            <h3 id={headingId} className="text-[length:var(--text-lg)]">
              {t("parts", { title: lesson.title })}
            </h3>
            <p className="text-sm text-muted">
              {lesson.shape ? `${t(`shapes.${lesson.shape}`)}: ${t(`shapeHelp.${lesson.shape}`)}` : t("noShape")}
            </p>
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-2">
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
            className="inline-flex items-center gap-1.5 rounded-control border border-rule-2 px-3 py-1.5 text-sm text-ink hover:bg-paper-3"
          >
            <X aria-hidden className="size-4" />
            {t("back")}
          </button>
        </div>
      </div>

      {parsed?.success && parsed.data.shape ? (
        <ArchitectureView
          architecture={{ ...parsed.data, shape: parsed.data.shape }}
          box={box}
          selected={selected}
          onSelect={setSelected}
          label={t("parts", { title: lesson.title })}
        />
      ) : parsed ? (
        <p className="text-sm text-muted">{t("unavailable")}</p>
      ) : (
        <div className="flex flex-1 items-center justify-center px-6 text-center text-ink-2">{lesson.problem}</div>
      )}

      <div aria-live="polite" className="min-h-16 border-t border-rule pt-2 text-sm">
        {part ? (
          <div className="flex flex-col gap-1.5">
            <p>
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
    </div>
  );
}
