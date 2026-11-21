"use client";

import { Flame } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import type { ReactNode } from "react";
import { Alert } from "@/components/ui/alert";
import { ProgressTrack } from "@/features/assessment/components/parts";
import { useCurrentUser } from "@/features/auth/hooks";
import { cn } from "@/lib/utils";
import { useSummary } from "../hooks";
import type { ProgressSummary } from "../types";
import { ActivityCharts } from "./activity-charts";
import { useMinutes } from "./format";
import { AssessmentHistoryPanel, CoursesPanel, linkClass, NextLessonsPanel, RecentActivityPanel, SkillsPanel } from "./panels";

/** The learner's home (D35): where I stand, what to study next, and how my study has gone. */
export function DashboardView() {
  const t = useTranslations("dashboard");
  const { data: user } = useCurrentUser();
  const summary = useSummary();
  const firstName = user?.name.split(" ")[0] ?? "";

  return (
    <div className="container-page flex flex-col gap-8 py-[var(--space-xl)]">
      <header className="flex flex-col gap-2">
        <h1 className="text-[length:var(--text-display-s)]">{t("title", { name: firstName })}</h1>
        {summary.data ? <StreakLine streak={summary.data.streak} /> : null}
      </header>

      {summary.isError ? (
        <Alert tone="error">{t("loadError")}</Alert>
      ) : summary.data ? (
        <Ledger summary={summary.data} />
      ) : (
        <div role="status" aria-label={t("loading")} className="h-32" />
      )}

      <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div className="flex min-w-0 flex-col gap-6">
          <NextLessonsPanel />
          <ActivityCharts goalMinutes={summary.data?.week.goal_minutes ?? null} />
          <AssessmentHistoryPanel />
        </div>
        <div className="flex min-w-0 flex-col gap-6">
          <RecentActivityPanel />
          <SkillsPanel />
          <CoursesPanel />
        </div>
      </div>
    </div>
  );
}

/** One sentence on the streak: what it is and what keeps it going. */
function StreakLine({ streak }: { streak: ProgressSummary["streak"] }) {
  const t = useTranslations("dashboard.streak");
  const key = streak.current === 0 ? (streak.longest > 0 ? "lapsed" : "none") : streak.active_today ? "today" : "keep";
  return (
    <p className="flex items-center gap-2 text-[length:var(--text-lead)] text-ink-2">
      {streak.current > 0 ? <Flame aria-hidden className="size-5 shrink-0 text-attn" /> : null}
      {t(key, { days: streak.current, longest: streak.longest })}
    </p>
  );
}

function Figure({ label, value, children, className }: { label: string; value: ReactNode; children?: ReactNode; className?: string }) {
  return (
    <div className={cn("flex min-w-0 flex-col gap-1.5 px-5 py-4", className)}>
      <dt className="text-sm text-ink-2">{label}</dt>
      <dd className="flex flex-col gap-1.5">
        <span className="text-[length:var(--text-title)] font-semibold text-ink">{value}</span>
        {children}
      </dd>
    </div>
  );
}

/** The numbers that summarise my learning, read left to right as one line. */
function Ledger({ summary }: { summary: ProgressSummary }) {
  const t = useTranslations("dashboard.figures");
  const minutes = useMinutes();
  const { week, streak, roadmap } = summary;

  return (
    <dl className="panels grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
      <Figure label={t("streak")} value={t("days", { count: streak.current })}>
        <span className="text-sm text-muted">{t("longest", { count: streak.longest })}</span>
      </Figure>
      <Figure label={t("week")} value={minutes(week.minutes)}>
        {week.goal_minutes ? (
          <>
            <ProgressTrack value={Math.round((week.minutes / week.goal_minutes) * 100)} />
            <span className="text-sm text-muted">{t("ofGoal", { goal: minutes(week.goal_minutes) })}</span>
          </>
        ) : (
          <Link href="/settings/goals" className={cn(linkClass, "text-sm")}>
            {t("setGoal")}
          </Link>
        )}
      </Figure>
      <Figure label={t("lessons")} value={summary.completed_lessons} />
      <Figure label={t("courses")} value={t("coursesValue", { completed: summary.completed_courses, enrolled: summary.enrolled_courses })}>
        <span className="text-sm text-muted">{t("coursesHint", { count: summary.enrolled_courses })}</span>
      </Figure>
      <Figure label={t("total")} value={minutes(summary.total_minutes)} />
      <Figure label={t("roadmap")} value={roadmap ? `${roadmap.completion_percent}%` : "—"}>
        {roadmap ? (
          <>
            <ProgressTrack value={roadmap.completion_percent} />
            <Link href="/roadmap" className={cn(linkClass, "truncate text-sm")}>
              {roadmap.career}
            </Link>
          </>
        ) : (
          <Link href="/roadmap" className={cn(linkClass, "text-sm")}>
            {t("buildRoadmap")}
          </Link>
        )}
      </Figure>
    </dl>
  );
}
