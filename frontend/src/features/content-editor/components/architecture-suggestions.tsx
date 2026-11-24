"use client";

import { Check, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import type { Architecture } from "@/lib/graph/shapes/schemas";
import { fillFromBlock } from "../architecture-fill";
import { useDismissed } from "../map-suggestions";
import type { AdminLesson, LessonSuggestion, WorkingCopy } from "../types";

const EMPTY: Architecture = { version: 1, problem: "", shape: null, nodes: [], edges: [] };

/**
 * Rule-based starts for a lesson's architecture (F4 Part C, D63): the problem drafted from the
 * summary and a shape for each block that has one. Using one only changes the working copy; it is
 * saved with the lesson draft. Shown while they still apply (no problem, no shape yet).
 */
export function ArchitectureSuggestions({
  lesson,
  copy,
  onUse,
}: {
  lesson: AdminLesson;
  copy: WorkingCopy;
  onUse: (architecture: Architecture) => void;
}) {
  const t = useTranslations("content.editor.architecture.suggest");
  const tm = useTranslations("courseMap");
  const { dismissed, dismiss } = useDismissed(`lesson-${lesson.id}`);
  const arch = copy.architecture;
  const keyOf = (s: LessonSuggestion) => (s.type === "problem" ? `problem:${s.problem}` : `shape:${s.section}:${s.shape}`);
  const open = lesson.architecture_suggestions.filter((s) => {
    if (dismissed.has(keyOf(s))) return false;
    if (s.type === "problem") return !arch?.problem.trim();
    // A shape needs its block still in the lesson, and an architecture without a shape.
    return !arch?.shape && copy.sections.some((x) => x.id === s.section);
  });
  if (!open.length) return null;

  const use = (s: LessonSuggestion) => {
    if (s.type === "problem") return onUse({ ...(arch ?? EMPTY), problem: s.problem });
    const filled = fillFromBlock(copy.sections.find((x) => x.id === s.section)!, arch?.problem ?? "");
    if (filled) onUse(s.shape === filled.shape ? filled : { ...filled, shape: s.shape });
  };

  return (
    <div className="flex flex-col gap-2 rounded-control bg-paper-3 p-3 text-sm">
      <p className="font-medium text-ink">{t("title")}</p>
      <ul className="flex flex-col gap-2">
        {open.map((s) => {
          const what =
            s.type === "problem"
              ? t("problem", { problem: s.problem })
              : t("shape", { shape: tm(`shapes.${s.shape}`), block: s.heading ?? s.block });
          return (
            <li key={keyOf(s)} className="flex flex-wrap items-center justify-between gap-2">
              <span className="max-w-[60ch] text-ink-2">{what}</span>
              <span className="flex gap-1">
                <Button type="button" variant="secondary" size="sm" aria-label={`${t("use")}: ${what}`} onClick={() => use(s)}>
                  <Check aria-hidden />
                  {t("use")}
                </Button>
                <Button type="button" variant="ghost" size="sm" aria-label={`${t("dismiss")}: ${what}`} onClick={() => dismiss(keyOf(s))}>
                  <X aria-hidden />
                  {t("dismiss")}
                </Button>
              </span>
            </li>
          );
        })}
      </ul>
      <p className="text-muted">{t("hint")}</p>
    </div>
  );
}
