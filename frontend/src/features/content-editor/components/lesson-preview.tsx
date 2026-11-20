"use client";

import { CircleCheck, Target } from "lucide-react";
import { useTranslations } from "next-intl";
import { Alert } from "@/components/ui/alert";
import { renderInline } from "@/features/learning/components/prose";
import { Section } from "@/features/learning/components/sections";
import type { AdminLesson } from "../types";

/**
 * The lesson as learners will see it: the learner's own Section renderer fed with
 * `preview_sections`, which the API serializes exactly like the reader endpoint. It shows
 * the last saved working copy, so what you preview is what Publish will make live.
 */
export function LessonPreview({ lesson, dirty }: { lesson: AdminLesson; dirty: boolean }) {
  const t = useTranslations();
  const copy = lesson.working_copy;

  return (
    <div className="flex flex-col gap-6">
      {dirty ? <Alert tone="info">{t("content.editor.previewStale")}</Alert> : null}
      <div className="panel overflow-hidden">
        <p className="border-b border-rule bg-paper-3 px-5 py-2 text-xs text-muted">
          {t("content.editor.previewFrame")}
        </p>
        <article className="mx-auto flex max-w-[44rem] min-w-0 flex-col gap-10 px-5 py-8 text-[1.0625rem] leading-[1.7] text-ink-2 sm:px-8">
          <header className="flex flex-col gap-4">
            <h1 className="text-[length:var(--text-display-s)]">{copy.title}</h1>
            {copy.summary ? <p className="text-[length:var(--text-lead)] text-ink-2">{copy.summary}</p> : null}
          </header>
          {copy.objectives.length > 0 ? (
            <section className="flex flex-col gap-3 rounded-card border border-rule p-5">
              <h2 className="flex items-center gap-2 text-base font-medium text-ink">
                <Target aria-hidden className="size-4 text-accent" />
                {t("learn.objectives")}
              </h2>
              <ul className="flex list-disc flex-col gap-1.5 pl-5 text-base">
                {copy.objectives.map((o, i) => (
                  <li key={i}>{renderInline(o)}</li>
                ))}
              </ul>
            </section>
          ) : null}
          {lesson.preview_sections.map((section) => (
            <Section key={section.id} section={section} />
          ))}
          {copy.key_takeaways.length > 0 ? (
            <section className="flex flex-col gap-3 border-t border-rule pt-8">
              <h2 className="text-[length:var(--text-title)]">{t("learn.takeaways")}</h2>
              <ul className="flex flex-col gap-2">
                {copy.key_takeaways.map((k, i) => (
                  <li key={i} className="flex gap-2.5">
                    <CircleCheck aria-hidden className="mt-1.5 size-4 shrink-0 text-ok" />
                    <span>{renderInline(k)}</span>
                  </li>
                ))}
              </ul>
            </section>
          ) : null}
        </article>
      </div>
    </div>
  );
}
