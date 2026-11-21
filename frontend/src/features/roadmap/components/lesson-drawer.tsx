"use client";

import { ArrowRight, Check, Circle, CircleDot } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { Dialog } from "@/components/ui/dialog";
import { buttonVariants } from "@/components/ui/button";
import { Duration } from "@/features/catalog/components/labels";
import type { PathLesson } from "../types";

export type DrawerLesson = { lesson: PathLesson; topic: string };

/**
 * Lesson details opened from a graph node: a side sheet on the native dialog, so focus is
 * trapped, Escape closes it and focus returns to the lesson row that opened it.
 */
export function LessonDrawer({ value, onClose }: { value: DrawerLesson | null; onClose: () => void }) {
  const t = useTranslations("roadmap");
  const lesson = value?.lesson;
  const Icon = lesson?.status === "completed" ? Check : lesson?.status === "started" ? CircleDot : Circle;
  const action = lesson?.status === "completed" ? "review" : lesson?.status === "started" ? "continue" : "start";

  return (
    <Dialog
      open={value !== null}
      onClose={onClose}
      title={lesson?.title ?? ""}
      description={value ? t("drawer.topic", { topic: value.topic }) : undefined}
      closeLabel={t("drawer.close")}
      className="my-0 mr-0 ml-auto h-dvh max-h-dvh w-[min(26rem,100vw)] rounded-none border-y-0 border-r-0"
    >
      {lesson ? (
        <div className="flex flex-col gap-5">
          <p className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink">
            <span className="inline-flex items-center gap-1.5">
              <Icon aria-hidden className={lesson.status === "completed" ? "size-4 text-accent" : "size-4 text-muted"} />
              {t(`lessonStatus.${lesson.status}`)}
            </span>
            <span>{lesson.is_required ? t("drawer.required") : t("optional")}</span>
            <Duration minutes={lesson.estimated_minutes} />
            {lesson.exercise ? <span>{lesson.exercise.passed ? t("drawer.exercisePassed") : t("drawer.exerciseTodo")}</span> : null}
          </p>
          {lesson.summary ? <p className="text-ink-2">{lesson.summary}</p> : null}
          <Link href={`/learn/${lesson.slug}`} className={buttonVariants({ className: "self-start" })}>
            {t(`drawer.${action}`)}
            <ArrowRight aria-hidden />
          </Link>
        </div>
      ) : null}
    </Dialog>
  );
}
