"use client";

import { Check, Circle, CircleDot } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { cn } from "@/lib/utils";
import { useCourseProgress } from "../hooks";
import type { CourseDetail, LessonStatus } from "../types";
import { Duration } from "./labels";

const statusIcon: Record<LessonStatus, typeof Check> = { completed: Check, started: CircleDot, not_started: Circle };

/** Chapters and lessons. Signed-in learners also see each lesson's status, as an icon with text for screen readers. */
export function Curriculum({ course }: { course: Pick<CourseDetail, "slug" | "chapters"> }) {
  const t = useTranslations("course");
  const { data: progress } = useCourseProgress(course.slug);

  return (
    <ol className="flex flex-col gap-6">
      {course.chapters.map((chapter) => (
        <li key={chapter.position} className="flex flex-col gap-3">
          <h3 className="flex items-baseline gap-3 text-[length:var(--text-lg)]">
            <span className="measured text-sm font-normal text-muted">{t("chapter", { number: chapter.position })}</span>
            {chapter.title}
          </h3>
          <ol className="panels">
            {chapter.lessons.map((lesson) => {
              const status = progress?.lessons[lesson.slug];
              const Icon = status ? statusIcon[status] : null;
              return (
                <li key={lesson.slug}>
                  <Link
                    href={`/learn/${lesson.slug}`}
                    className="group flex items-start gap-3 px-4 py-3.5 transition-[background-color] duration-[var(--dur-micro)] hover:bg-paper-3"
                  >
                    {Icon && status ? (
                      <span className="mt-0.5 shrink-0">
                        <Icon aria-hidden className={cn("size-4", status === "completed" ? "text-accent" : "text-muted")} />
                        <span className="sr-only">{t(`lessonStatus.${status}`)}</span>
                      </span>
                    ) : null}
                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                      <span className="font-medium text-ink group-hover:underline group-hover:decoration-rule-2 group-hover:underline-offset-4">
                        {lesson.title}
                      </span>
                      <span className="text-sm">{lesson.summary}</span>
                    </span>
                    <Duration minutes={lesson.estimated_minutes} className="shrink-0 text-sm text-ink" />
                  </Link>
                </li>
              );
            })}
          </ol>
        </li>
      ))}
    </ol>
  );
}
