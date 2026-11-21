"use client";

import { ArrowRight, Check, FileSearch, Globe, Shield, SquareTerminal } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { Alert } from "@/components/ui/alert";
import { buttonVariants } from "@/components/ui/button";
import { ProgressTrack } from "@/features/assessment/components/parts";
import { DifficultyChip, Duration } from "@/features/catalog/components/labels";
import { useLabs } from "../hooks";
import type { Engine, LabSummary } from "../types";

export const DIFFICULTY = ["beginner", "intermediate", "advanced"] as const;
export const ENGINE_ICON: Record<Engine, typeof Shield> = { "linux-shell": SquareTerminal, "firewall-rules": Shield, "http-inspector": Globe };

/** The labs on offer, with my progress in each. */
export function LabsList() {
  const t = useTranslations("labs");
  const labs = useLabs();

  return (
    <div className="container-page flex flex-col gap-8 py-[var(--space-xl)]">
      <header className="flex max-w-[44rem] flex-col gap-3">
        <h1 className="text-[length:var(--text-display-s)]">{t("title")}</h1>
        <p className="text-[length:var(--text-lead)] text-ink-2">{t("intro")}</p>
        <p className="flex items-start gap-2 text-sm">
          <FileSearch aria-hidden className="mt-0.5 size-4 shrink-0 text-muted" />
          {t("simulated")}
        </p>
      </header>
      {labs.isError ? (
        <Alert tone="error">{t("loadError")}</Alert>
      ) : !labs.data ? (
        <div role="status" className="min-h-[40vh]" />
      ) : (
        <ol className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {labs.data.map((lab) => (
            <LabCard key={lab.slug} lab={lab} />
          ))}
        </ol>
      )}
    </div>
  );
}

function LabCard({ lab }: { lab: LabSummary }) {
  const t = useTranslations("labs");
  const Icon = ENGINE_ICON[lab.engine];
  const { progress } = lab;
  const action = progress.completed ? "review" : progress.started ? "continue" : "start";

  return (
    <li className="panel flex flex-col gap-4 p-5">
      <div className="flex items-center justify-between gap-3 text-sm">
        <span className="inline-flex items-center gap-1.5 text-ink">
          <Icon aria-hidden className="size-4 text-accent" />
          {t(`engines.${lab.engine}`)}
        </span>
        <span>{lab.domain.name}</span>
      </div>
      <div className="flex flex-col gap-2">
        <h2 className="text-[length:var(--text-lg)]">
          <Link href={`/labs/${lab.slug}`} className="hover:underline hover:decoration-accent hover:underline-offset-4">
            {lab.title}
          </Link>
        </h2>
        <p className="text-sm text-ink-2">{lab.summary}</p>
      </div>
      <p className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
        <DifficultyChip difficulty={DIFFICULTY[lab.difficulty - 1]} />
        <Duration minutes={lab.estimated_minutes} className="text-ink" />
      </p>
      <div className="mt-auto flex flex-col gap-2">
        <p className="flex items-center justify-between gap-3 text-sm">
          <span className="inline-flex items-center gap-1.5 text-ink">
            {progress.completed ? <Check aria-hidden className="size-4 text-accent" /> : null}
            {progress.completed ? t("completed") : t("checkpoints", { passed: progress.passed, total: progress.total })}
          </span>
          {lab.lesson ? (
            <Link href={`/learn/${lab.lesson.slug}`} className="truncate text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent">
              {lab.lesson.title}
            </Link>
          ) : null}
        </p>
        <ProgressTrack value={Math.round((progress.passed / Math.max(1, progress.total)) * 100)} />
        <Link href={`/labs/${lab.slug}`} className={buttonVariants({ variant: action === "start" ? "primary" : "secondary", className: "mt-2 self-start" })}>
          {t(`action.${action}`)}
          <ArrowRight aria-hidden />
        </Link>
      </div>
    </li>
  );
}
