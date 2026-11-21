"use client";

import { BookOpen, CircleCheck, Target } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { renderInline } from "@/features/learning/components/prose";
import { Section } from "@/features/learning/components/sections";
import type { Lesson } from "@/features/learning/types";

/**
 * The theory beside the exercise (master prompt Module F, left panel): the lesson section that
 * teaches the current question, drawn by the lesson's own block renderers, then the key concepts
 * and the learning objectives.
 */
export function TheoryPanel({ lesson, sectionId }: { lesson: Lesson; sectionId: string | null }) {
  const t = useTranslations("exercise.theory");
  const section = lesson.sections.find((s) => s.id === sectionId) ?? null;

  return (
    <div className="flex flex-col gap-8 text-base leading-[1.7] text-ink-2">
      <section aria-labelledby="theory-section" className="flex flex-col gap-3">
        <h2 id="theory-section" className="flex items-center gap-2 font-sans text-sm font-medium text-ink">
          <BookOpen aria-hidden className="size-4 text-accent" />
          {t("forQuestion")}
        </h2>
        {section ? (
          <div className="flex flex-col gap-4 rounded-control border border-rule bg-paper-2 p-4">
            <Section section={section} />
            <Link
              href={`/learn/${lesson.slug}#${section.id}`}
              className="self-start text-sm text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent"
            >
              {t("inLesson")}
            </Link>
          </div>
        ) : (
          <p className="text-sm">{t("noSection")}</p>
        )}
      </section>

      <section aria-labelledby="theory-concepts" className="flex flex-col gap-3">
        <h2 id="theory-concepts" className="text-[length:var(--text-lg)]">
          {t("concepts")}
        </h2>
        <ul className="flex flex-col gap-2">
          {lesson.key_takeaways.map((k) => (
            <li key={k} className="flex gap-2.5">
              <CircleCheck aria-hidden className="mt-1.5 size-4 shrink-0 text-accent" />
              <span>{renderInline(k)}</span>
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="theory-objectives" className="flex flex-col gap-3">
        <h2 id="theory-objectives" className="flex items-center gap-2 text-[length:var(--text-lg)]">
          <Target aria-hidden className="size-4 text-accent" />
          {t("objectives")}
        </h2>
        <ul className="flex flex-col gap-1.5 pl-5 [list-style:disc] marker:text-muted">
          {lesson.objectives.map((o) => (
            <li key={o}>{o}</li>
          ))}
        </ul>
      </section>
    </div>
  );
}
