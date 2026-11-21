"use client";

import { useFormatter, useTranslations } from "next-intl";
import { splitMinutes } from "@/lib/format";

/** A `Y-m-d` local date from the API, as a Date at UTC midnight (format it with timeZone UTC). */
export const day = (date: string) => new Date(`${date}T00:00:00Z`);

/** The running total after each step, starting from `before`. */
export function runningTotals(before: number, steps: number[]): number[] {
  return steps.reduce<number[]>((totals, step) => [...totals, (totals[totals.length - 1] ?? before) + step], []);
}

/** "45 min", "2 h", "1 h 30 min". */
export function useMinutes() {
  const t = useTranslations("units");
  return (total: number) => {
    const { hours, minutes } = splitMinutes(total);
    return hours === 0 ? t("minutes", { minutes }) : minutes === 0 ? t("hours", { hours }) : t("hoursMinutes", { hours, minutes });
  };
}

/** Axis ticks: minutes below an hour, then hours with at most one decimal ("1.5 h"), so ticks stay short. */
export function useMinuteTicks() {
  const t = useTranslations("units");
  const format = useFormatter();
  return (total: number) => (total < 60 ? t("minutes", { minutes: total }) : t("hours", { hours: format.number(total / 60, { maximumFractionDigits: 1 }) }));
}

/** Short and long date labels for an API date. */
export function useDayLabels() {
  const format = useFormatter();
  return {
    short: (date: string) => format.dateTime(day(date), { month: "short", day: "numeric", timeZone: "UTC" }),
    long: (date: string) => format.dateTime(day(date), { day: "numeric", month: "long", year: "numeric", timeZone: "UTC" }),
  };
}
