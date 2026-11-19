"use client";

import { Gauge } from "lucide-react";
import { useTranslations } from "next-intl";
import { splitMinutes } from "@/lib/format";
import { cn } from "@/lib/utils";
import type { Difficulty } from "../types";

/** "1 h 30 min" / "45 min", in the measured face. */
export function Duration({ minutes, className }: { minutes: number; className?: string }) {
  const t = useTranslations("units");
  const { hours, minutes: rest } = splitMinutes(minutes);
  const label =
    hours === 0 ? t("minutes", { minutes: rest }) : rest === 0 ? t("hours", { hours }) : t("hoursMinutes", { hours, minutes: rest });
  return <span className={cn("measured", className)}>{label}</span>;
}

/** Difficulty as a chip: icon plus words, with a three-step meter that never relies on colour alone. */
export function DifficultyChip({ difficulty, className }: { difficulty: Difficulty; className?: string }) {
  const t = useTranslations("difficulty");
  const level = { beginner: 1, intermediate: 2, advanced: 3 }[difficulty];
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-chip border border-rule px-1.5 py-0.5 text-sm leading-none text-ink",
        className,
      )}
    >
      <Gauge aria-hidden className="size-3.5 text-muted" />
      {t(difficulty)}
      <span aria-hidden className="ml-0.5 inline-flex gap-0.5">
        {[1, 2, 3].map((n) => (
          <span key={n} className={cn("h-2.5 w-1 rounded-[1px]", n <= level ? "bg-ink-2" : "bg-rule")} />
        ))}
      </span>
    </span>
  );
}
