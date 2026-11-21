"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { SegmentedControl } from "@/components/ui/segmented-control";
import { ChartTable, type Datum } from "@/lib/charts/chart-parts";
import { ColumnChart } from "@/lib/charts/column-chart";
import { LineChart } from "@/lib/charts/line-chart";
import { MINUTE_STEPS, stepTicks } from "@/lib/charts/scale";
import { cn } from "@/lib/utils";
import { useWeeklyActivity } from "../hooks";
import { RANGES, type Range, type WeeklyActivity } from "../types";
import { runningTotals, useDayLabels, useMinuteTicks, useMinutes } from "./format";

/**
 * Study over time: minutes per week against the weekly goal, and the running total of lessons
 * completed. One range control above both charts scopes them together.
 */
export function ActivityCharts({ goalMinutes }: { goalMinutes: number | null }) {
  const t = useTranslations("dashboard.activity");
  const [range, setRange] = useState<Range>("12w");
  const activity = useWeeklyActivity(range);

  return (
    <section aria-labelledby="activity-heading" className="panel flex flex-col gap-6 p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 id="activity-heading" className="text-[length:var(--text-title)]">
          {t("title")}
        </h2>
        <SegmentedControl label={t("range")} options={RANGES.map((value) => ({ value, label: t(`ranges.${value}`) }))} value={range} onChange={setRange} />
      </div>
      {activity.isError ? (
        <Alert tone="error">{t("loadError")}</Alert>
      ) : !activity.data ? (
        <div role="status" aria-label={t("loading")} className="h-[26rem]" />
      ) : (
        <div className={cn("flex flex-col gap-8 transition-opacity", activity.isPlaceholderData && "opacity-60")}>
          <WeeklyMinutes data={activity.data} goalMinutes={goalMinutes} />
          <LessonsOverTime data={activity.data} />
        </div>
      )}
    </section>
  );
}

function WeeklyMinutes({ data, goalMinutes }: { data: WeeklyActivity; goalMinutes: number | null }) {
  const t = useTranslations("dashboard.activity");
  const minutes = useMinutes();
  const tick = useMinuteTicks();
  const days = useDayLabels();
  const rows: Datum[] = data.weeks.map((w) => ({
    key: w.starts_on,
    label: days.short(w.starts_on),
    detail: t("weekOf", { date: days.long(w.starts_on) }),
    value: w.minutes,
    display: minutes(w.minutes),
  }));
  const total = data.weeks.reduce((sum, w) => sum + w.minutes, 0);
  const metGoal = goalMinutes ? data.weeks.filter((w) => w.minutes >= goalMinutes).length : 0;

  return (
    <figure className="flex flex-col gap-3">
      <figcaption className="flex flex-col gap-1">
        <span className="font-medium text-ink">{t("minutesTitle")}</span>
        <span className="text-sm text-ink-2">
          {goalMinutes
            ? t("minutesSummaryGoal", { total: minutes(total), weeks: data.weeks.length, met: metGoal })
            : t("minutesSummary", { total: minutes(total), weeks: data.weeks.length })}
        </span>
      </figcaption>
      <ColumnChart
        label={t("minutesTitle")}
        data={rows}
        formatTick={tick}
        floor={30}
        tickValues={(max) => stepTicks(max, MINUTE_STEPS)}
        reference={goalMinutes ? { value: goalMinutes, label: t("goal", { minutes: minutes(goalMinutes) }) } : undefined}
        className="mt-6"
      />
      <ChartTable data={rows} text={{ toggle: t("showTable"), columns: [t("week"), t("studied")] }} />
    </figure>
  );
}

function LessonsOverTime({ data }: { data: WeeklyActivity }) {
  const t = useTranslations("dashboard.activity");
  const days = useDayLabels();
  const totals = runningTotals(data.lessons_completed_before, data.weeks.map((w) => w.lessons_completed));
  const rows: Datum[] = data.weeks.map((w, i) => ({
    key: w.starts_on,
    label: days.short(w.starts_on),
    detail: t("weekOf", { date: days.long(w.starts_on) }),
    value: totals[i],
    display: t("lessonCount", { count: totals[i] }),
  }));
  const running = totals[totals.length - 1] ?? data.lessons_completed_before;
  const added = running - data.lessons_completed_before;

  return (
    <figure className="flex flex-col gap-3">
      <figcaption className="flex flex-col gap-1">
        <span className="font-medium text-ink">{t("lessonsTitle")}</span>
        <span className="text-sm text-ink-2">{t("lessonsSummary", { added, weeks: data.weeks.length, total: running })}</span>
      </figcaption>
      <LineChart label={t("lessonsTitle")} data={rows} formatTick={(v) => String(v)} floor={5} className="mt-6" />
      <ChartTable data={rows} text={{ toggle: t("showTable"), columns: [t("week"), t("lessonsTotal")] }} />
    </figure>
  );
}
