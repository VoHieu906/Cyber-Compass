"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { DomainIcon } from "../domain-icons";
import type { CourseSummary } from "../types";
import { CourseThumbnail } from "./course-thumbnail";
import { FavoriteButton } from "./favorite-button";
import { DifficultyChip, Duration } from "./labels";

/**
 * A catalog card. The title is the link (its ::after covers the card, so the whole card
 * is clickable) and the favorite button sits above that layer as a separate control.
 */
export function CourseCard({ course, compact = false, showSummary = true }: { course: CourseSummary; compact?: boolean; showSummary?: boolean }) {
  const t = useTranslations();
  return (
    <article className="relative flex h-full flex-col bg-paper-2">
      {compact ? null : <CourseThumbnail course={course} />}
      <div className="flex flex-1 flex-col gap-3 p-5">
        <div className="flex items-start justify-between gap-3">
          <h3 className="flex items-start gap-2.5 text-[length:var(--text-lg)] leading-snug">
            {compact ? <DomainIcon slug={course.domain.slug} className="mt-1 size-4 shrink-0 text-muted" /> : null}
            <Link
              href={`/courses/${course.slug}`}
              className="rounded-chip after:absolute after:inset-0 after:content-[''] focus-visible:outline-offset-4"
            >
              {course.title}
            </Link>
          </h3>
          <FavoriteButton
            slug={course.slug}
            title={course.title}
            favorited={Boolean(course.is_favorited)}
            className="relative z-[1] shrink-0"
          />
        </div>
        {showSummary ? <p className="line-clamp-3 text-sm">{course.summary}</p> : null}
        <dl className="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 pt-1 text-sm">
          <div>
            <dt className="sr-only">{t("catalog.difficulty")}</dt>
            <dd>
              <DifficultyChip difficulty={course.difficulty} />
            </dd>
          </div>
          <div>
            <dt className="sr-only">{t("catalog.domain")}</dt>
            <dd className="text-ink">{course.domain.name}</dd>
          </div>
          <div className="flex gap-4">
            <dt className="sr-only">{t("course.curriculum")}</dt>
            <dd className="measured text-ink">{t("units.lessons", { count: course.lesson_count })}</dd>
            <dd>
              <Duration minutes={course.estimated_minutes} className="text-ink" />
            </dd>
          </div>
        </dl>
      </div>
    </article>
  );
}
