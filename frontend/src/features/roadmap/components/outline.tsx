"use client";

import { Check, ChevronDown, Circle, CircleDot } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useId, useState, type ReactNode } from "react";
import { Duration } from "@/features/catalog/components/labels";
import { cn } from "@/lib/utils";
import type { PathLesson } from "../types";
import { RoadmapNodeCard, type NodeStatus } from "./node-status";

/** The graphs need room for their columns; below this the outline is the view (D25, D27). */
export const WIDE = "(min-width: 48rem)";

export const linkClass = "text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent";

/** One topic in the outline (phones, ARCHITECTURE D25): its card, why it is there, and its lessons. */
export function OutlineTopic({
  title,
  status,
  percent,
  review,
  meta,
  details,
  lessons,
  startOpen = false,
}: {
  title: string;
  status: NodeStatus;
  percent: number;
  review?: boolean;
  meta?: string;
  details: ReactNode;
  lessons: PathLesson[];
  startOpen?: boolean;
}) {
  const t = useTranslations("roadmap");
  const [open, setOpen] = useState(startOpen);
  const panelId = useId();

  return (
    <li className="grid gap-3 md:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] md:gap-6">
      <RoadmapNodeCard
        title={title}
        status={status}
        statusLabel={t(`status.${status}`)}
        percent={percent}
        review={review ? { label: t("reviewChip") } : undefined}
        meta={meta}
        className={cn("md:self-start", startOpen && "ring-2 ring-accent ring-offset-2 ring-offset-paper")}
      />
      <div className="flex min-w-0 flex-col gap-2">
        {details}
        <button
          type="button"
          aria-expanded={open}
          aria-controls={panelId}
          onClick={() => setOpen((v) => !v)}
          className="inline-flex items-center gap-1.5 self-start rounded-control py-1 text-sm font-medium text-ink hover:text-accent"
        >
          <ChevronDown aria-hidden className={cn("size-4 transition-transform duration-[var(--dur-short)]", open && "rotate-180")} />
          {t("lessons", { count: lessons.length })}
        </button>
        <ul id={panelId} hidden={!open} className="flex flex-col border-t border-rule">
          {lessons.map((lesson) => (
            <LessonRow key={lesson.slug} lesson={lesson} />
          ))}
        </ul>
      </div>
    </li>
  );
}

function LessonRow({ lesson }: { lesson: PathLesson }) {
  const t = useTranslations("roadmap");
  const Icon = lesson.status === "completed" ? Check : lesson.status === "started" ? CircleDot : Circle;
  return (
    <li className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-rule py-2.5 text-sm">
      <Icon aria-hidden className={cn("size-4 shrink-0", lesson.status === "completed" ? "text-accent" : "text-muted")} />
      <Link href={`/learn/${lesson.slug}`} className={cn(linkClass, "min-w-0 flex-1")}>
        {lesson.title}
      </Link>
      <span className="sr-only">{t(`lessonStatus.${lesson.status}`)}</span>
      {lesson.is_required ? null : <span className="rounded-chip border border-rule px-1.5">{t("optional")}</span>}
      <Duration minutes={lesson.estimated_minutes} />
    </li>
  );
}
