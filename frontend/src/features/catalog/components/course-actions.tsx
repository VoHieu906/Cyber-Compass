"use client";

import { ArrowRight, CircleCheck } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { useCurrentUser } from "@/features/auth/hooks";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { useCourseProgress, useEnroll } from "../hooks";
import type { CourseDetail } from "../types";
import { FavoriteButton } from "./favorite-button";

/** The course page's call to action, which depends on who is looking and how far they are. */
export function CourseActions({ course }: { course: Pick<CourseDetail, "slug" | "title" | "chapters"> }) {
  const t = useTranslations("course");
  const router = useRouter();
  const errorMessage = useErrorMessage();
  const { data: user, isPending: userPending } = useCurrentUser();
  const progress = useCourseProgress(course.slug);
  const enroll = useEnroll(course.slug);
  const firstLesson = course.chapters[0]?.lessons[0];

  if (userPending || (user && progress.isPending)) {
    return <div aria-hidden className="h-[88px]" />;
  }

  if (!user) {
    return (
      <div className="flex flex-wrap items-center gap-3">
        <Link href={`/login?next=${encodeURIComponent(`/courses/${course.slug}`)}`} className={buttonVariants()}>
          {t("signInToStart")}
          <ArrowRight aria-hidden />
        </Link>
        <FavoriteButton slug={course.slug} title={course.title} favorited={false} withLabel />
      </div>
    );
  }

  const p = progress.data;
  const done = p && p.total_lessons > 0 && p.completed_lessons === p.total_lessons;
  const target = done ? firstLesson?.slug : (p?.next_lesson?.slug ?? firstLesson?.slug);

  async function begin() {
    if (!p?.enrolled) await enroll.mutateAsync();
    if (target) router.push(`/learn/${target}`);
  }

  const label = done
    ? t("review")
    : p && p.completed_lessons > 0 && p.next_lesson
      ? t("continue", { title: p.next_lesson.title })
      : t("start");

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-3">
        <Button onClick={begin} loading={enroll.isPending} className="max-w-full">
          <span className="truncate">{label}</span>
          <ArrowRight aria-hidden />
        </Button>
        <FavoriteButton slug={course.slug} title={course.title} favorited={Boolean(p?.favorited)} withLabel />
      </div>
      {p?.enrolled ? (
        <div className="flex max-w-[28rem] flex-col gap-2">
          <div className="flex items-center justify-between gap-3 text-sm">
            <span className="inline-flex items-center gap-1.5 text-ink">
              {done ? <CircleCheck aria-hidden className="size-4 text-accent" /> : null}
              {done ? t("completedCourse") : t("enrolled")}
            </span>
            <span className="measured text-ink">{t("progress", { completed: p.completed_lessons, total: p.total_lessons })}</span>
          </div>
          <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={p.percent}
            aria-label={t("progress", { completed: p.completed_lessons, total: p.total_lessons })}
            className="h-1 overflow-hidden rounded-full bg-rule"
          >
            <div
              className="h-full w-full origin-left bg-accent transition-transform duration-[var(--dur-long)] ease-out"
              style={{ transform: `scaleX(${p.percent / 100})` }}
            />
          </div>
        </div>
      ) : null}
      {enroll.isError ? <Alert tone="error">{errorMessage(enroll.error)}</Alert> : null}
    </div>
  );
}
