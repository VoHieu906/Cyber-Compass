"use client";

import { ArrowRight, BookOpenCheck, Check, CircleCheck, ClipboardCheck, PenLine, Play, RotateCcw, SquareTerminal } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import type { ReactNode } from "react";
import { Alert } from "@/components/ui/alert";
import { buttonVariants } from "@/components/ui/button";
import { BandChip, ProgressTrack, ScoreBar } from "@/features/assessment/components/parts";
import { Duration } from "@/features/catalog/components/labels";
import { ChartTable, type Datum } from "@/lib/charts/chart-parts";
import { LineChart } from "@/lib/charts/line-chart";
import { cn } from "@/lib/utils";
import { useAssessmentHistory, useCourseCompletion, useNextLessons, useRecentActivity, useSkills } from "../hooks";
import type { Activity, ActivityType } from "../types";

export const linkClass = "text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent";

/** A titled dashboard panel with its own loading and error states. */
function Panel({ id, title, aside, error, loading, children, className }: { id: string; title: string; aside?: ReactNode; error: boolean; loading: boolean; children: ReactNode; className?: string }) {
  const t = useTranslations("dashboard");
  return (
    <section aria-labelledby={id} className={cn("panel flex flex-col gap-4 p-5", className)}>
      <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id={id} className="text-[length:var(--text-title)]">
          {title}
        </h2>
        {aside}
      </div>
      {error ? <Alert tone="error">{t("loadError")}</Alert> : loading ? <div role="status" aria-label={t("loading")} className="min-h-24" /> : children}
    </section>
  );
}

/** Up to three lessons to study next, from the roadmap or else from enrolled courses. */
export function NextLessonsPanel() {
  const t = useTranslations("dashboard.next");
  const next = useNextLessons();
  const lessons = next.data?.lessons ?? [];

  return (
    <Panel
      id="next-heading"
      title={t("title")}
      error={next.isError}
      loading={!next.data}
      aside={next.data?.source ? <span className="text-sm text-muted">{t(`source.${next.data.source}`)}</span> : null}
    >
      {lessons.length === 0 ? (
        <div className="flex flex-col gap-3">
          <p className="text-ink-2">{t("empty")}</p>
          <p className="flex flex-wrap gap-3">
            <Link href="/assessment" className={buttonVariants({ variant: "primary" })}>
              {t("takeAssessment")}
              <ArrowRight aria-hidden />
            </Link>
            <Link href="/courses" className={buttonVariants({ variant: "secondary" })}>
              {t("browseCourses")}
            </Link>
          </p>
        </div>
      ) : (
        <ol className="flex flex-col divide-y divide-rule">
          {lessons.map((lesson, i) => (
            <li key={lesson.slug} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 first:pt-0 last:pb-0">
              <div className="flex min-w-0 flex-col gap-0.5">
                <Link href={`/learn/${lesson.slug}`} className={cn("font-medium text-ink", i === 0 && "text-[length:var(--text-lg)]", "hover:underline hover:decoration-accent hover:underline-offset-4")}>
                  {lesson.title}
                </Link>
                <span className="flex flex-wrap gap-x-3 text-sm text-ink-2">
                  <span>{lesson.topic ?? lesson.course}</span>
                  <Duration minutes={lesson.estimated_minutes} className="text-muted" />
                </span>
              </div>
              {i === 0 ? (
                <Link href={`/learn/${lesson.slug}`} className={buttonVariants({ variant: "primary" })}>
                  {t("start")}
                  <ArrowRight aria-hidden />
                </Link>
              ) : null}
            </li>
          ))}
        </ol>
      )}
    </Panel>
  );
}

const ACTIVITY_ICON: Record<ActivityType, typeof Play> = {
  lesson_started: Play,
  lesson_completed: CircleCheck,
  study_session: RotateCcw,
  exercise_submitted: PenLine,
  assessment_submitted: ClipboardCheck,
  lab_completed: SquareTerminal,
};

function activityHref(activity: Activity): string | null {
  const s = activity.subject;
  if (!s) return null;
  if (s.kind === "lesson") return `/learn/${s.slug}`;
  if (s.kind === "lab") return `/labs/${s.slug}`;
  if (s.kind === "exercise") return `/learn/${s.lesson.slug}/exercise`;
  return `/assessment/attempts/${s.id}/result`;
}

function ActivityText({ activity }: { activity: Activity }) {
  const t = useTranslations("dashboard.recent");
  const s = activity.subject;
  const href = activityHref(activity);
  const link = (chunks: ReactNode) => (href ? <Link href={href} className={linkClass}>{chunks}</Link> : chunks);
  if (!s) return <>{t("removed")}</>;
  if (s.kind === "exercise") {
    return <>{t.rich(s.passed ? "exercisePassed" : "exerciseTried", { title: s.lesson.title, score: s.score, link })}</>;
  }
  if (s.kind === "assessment") return <>{t.rich("assessment", { score: s.score ?? 0, link })}</>;
  return <>{t.rich(activity.type, { title: s.title, link })}</>;
}

/** The latest things I did, newest first, with when. */
export function RecentActivityPanel() {
  const t = useTranslations("dashboard.recent");
  const format = useFormatter();
  const recent = useRecentActivity();
  const now = new Date();

  return (
    <Panel id="recent-heading" title={t("title")} error={recent.isError} loading={!recent.data}>
      {recent.data?.length === 0 ? (
        <p className="text-ink-2">{t("empty")}</p>
      ) : (
        <ol className="flex flex-col gap-3">
          {recent.data?.map((activity) => {
            const Icon = ACTIVITY_ICON[activity.type];
            return (
              <li key={activity.id} className="flex gap-3 text-sm">
                <Icon aria-hidden className="mt-0.5 size-4 shrink-0 text-muted" />
                <div className="flex min-w-0 flex-col gap-0.5">
                  <span className="text-ink">
                    <ActivityText activity={activity} />
                  </span>
                  <span className="text-muted">
                    <time dateTime={activity.occurred_at}>{format.relativeTime(new Date(activity.occurred_at), now)}</time>
                    {activity.minutes > 0 ? (
                      <>
                        {" · "}
                        <Duration minutes={activity.minutes} />
                      </>
                    ) : null}
                  </span>
                </div>
              </li>
            );
          })}
        </ol>
      )}
    </Panel>
  );
}

/** Latest assessment score and band per domain, with lesson completion in that domain. */
export function SkillsPanel() {
  const t = useTranslations("dashboard.skills");
  const skills = useSkills();
  const assessed = skills.data?.some((d) => d.score !== null) ?? false;

  return (
    <Panel
      id="skills-heading"
      title={t("title")}
      error={skills.isError}
      loading={!skills.data}
      aside={
        <Link href="/roadmap/map" className={cn(linkClass, "text-sm")}>
          {t("map")}
        </Link>
      }
    >
      {!assessed ? (
        <p className="text-sm text-ink-2">
          {t.rich("notAssessed", { link: (chunks) => <Link href="/assessment" className={linkClass}>{chunks}</Link> })}
        </p>
      ) : null}
      <ul className="flex flex-col gap-4">
        {skills.data?.map((domain) => (
          <li key={domain.slug} className="flex flex-col gap-1.5 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span className="font-medium text-ink">{domain.name}</span>
              {domain.band !== null && domain.score !== null ? (
                <span className="flex items-center gap-2">
                  <span className="measured text-ink">{domain.score}%</span>
                  <BandChip band={domain.band} />
                </span>
              ) : (
                <span className="text-muted">{t("noScore")}</span>
              )}
            </div>
            {domain.band !== null && domain.score !== null ? <ScoreBar score={domain.score} band={domain.band} /> : null}
            <span className="text-muted">
              {domain.completion.total ? t("lessons", { completed: domain.completion.completed, total: domain.completion.total }) : t("noLessons")}
            </span>
          </li>
        ))}
      </ul>
    </Panel>
  );
}

/** Completion of each enrolled course. */
export function CoursesPanel() {
  const t = useTranslations("dashboard.courses");
  const courses = useCourseCompletion();

  return (
    <Panel id="courses-heading" title={t("title")} error={courses.isError} loading={!courses.data}>
      {courses.data?.length === 0 ? (
        <p className="text-sm text-ink-2">
          {t.rich("empty", { link: (chunks) => <Link href="/courses" className={linkClass}>{chunks}</Link> })}
        </p>
      ) : (
        <ul className="flex flex-col gap-4">
          {courses.data?.map((course) => (
            <li key={course.slug} className="flex flex-col gap-1.5 text-sm">
              <Link href={`/courses/${course.slug}`} className="font-medium text-ink hover:underline hover:decoration-accent hover:underline-offset-4">
                {course.title}
              </Link>
              <ProgressTrack value={course.percent} />
              <span className="flex items-center gap-1.5 text-ink-2">
                {course.completed_at ? <Check aria-hidden className="size-4 text-accent" /> : null}
                {course.completed_at ? t("completed") : t("lessons", { completed: course.completed_lessons, total: course.total_lessons })}
              </span>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

/** Overall score across my assessments, oldest first. A chart only once there are two to compare. */
export function AssessmentHistoryPanel() {
  const t = useTranslations("dashboard.assessments");
  const format = useFormatter();
  const history = useAssessmentHistory();
  const items = history.data ?? [];
  const latest = items[items.length - 1];
  const rows: Datum[] = items.map((item, i) => ({
    key: String(item.id),
    label: format.dateTime(new Date(item.submitted_at), { month: "short", day: "numeric" }),
    detail: t("attempt", { n: i + 1, date: format.dateTime(new Date(item.submitted_at), { dateStyle: "medium" }) }),
    value: item.score,
    display: `${item.score}%`,
  }));

  return (
    <Panel
      id="assessments-heading"
      title={t("title")}
      error={history.isError}
      loading={!history.data}
      aside={
        items.length ? (
          <Link href="/assessment" className={cn(linkClass, "text-sm")}>
            {t("retake")}
          </Link>
        ) : null
      }
    >
      {!latest ? (
        <p className="text-sm text-ink-2">
          {t.rich("empty", { link: (chunks) => <Link href="/assessment" className={linkClass}>{chunks}</Link> })}
        </p>
      ) : (
        <div className="flex flex-col gap-4">
          <p className="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-ink-2">
            <BookOpenCheck aria-hidden className="size-4 text-muted" />
            {t("latest")}
            <span className="measured text-[length:var(--text-lg)] font-semibold text-ink">{latest.score}%</span>
            <BandChip band={latest.band} />
            <Link href={`/assessment/attempts/${latest.id}/result`} className={linkClass}>
              {t("result")}
            </Link>
          </p>
          {items.length > 1 ? (
            <figure className="flex flex-col gap-3">
              <figcaption className="text-sm text-ink-2">{t("summary", { count: items.length, first: items[0].score, last: latest.score })}</figcaption>
              <LineChart label={t("chart")} data={rows} formatTick={(v) => `${v}%`} max={100} className="mt-6" />
              <ChartTable data={rows} text={{ toggle: t("showTable"), columns: [t("attemptColumn"), t("score")] }} />
            </figure>
          ) : (
            <p className="text-sm text-muted">{t("single")}</p>
          )}
        </div>
      )}
    </Panel>
  );
}
