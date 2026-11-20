"use client";

import { ArrowRight, Info } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import { Alert } from "@/components/ui/alert";
import { buttonVariants } from "@/components/ui/button";
import { useActiveAssessment, useAttemptHistory } from "../hooks";
import { percent } from "../progress";
import type { ActiveAssessment } from "../types";
import { BandChip, FormulaPanel, ProgressTrack, StartAttemptButton } from "./parts";

export function AssessmentOverview() {
  const t = useTranslations("assessment.intro");
  const assessment = useActiveAssessment();

  if (assessment.isError) {
    return (
      <div className="container-page max-w-[44rem] py-[var(--space-2xl)]">
        <Alert tone="error">{t("loadError")}</Alert>
      </div>
    );
  }
  if (!assessment.data) return <div role="status" className="min-h-[60vh]" />;

  return <Overview assessment={assessment.data} />;
}

function Overview({ assessment }: { assessment: ActiveAssessment }) {
  const t = useTranslations("assessment");
  const format = useFormatter();
  const open = assessment.open_attempt;
  const latest = assessment.latest_result;

  return (
    <div className="container-page grid gap-10 py-[var(--space-2xl)] lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:gap-[var(--gap-columns)]">
      <div className="flex max-w-[44rem] min-w-0 flex-col gap-10">
        <header className="flex flex-col gap-5">
          <h1 className="text-[length:var(--text-display-s)]">{assessment.title}</h1>
          <p className="text-[length:var(--text-lead)] text-ink-2">{assessment.description}</p>
          <p className="flex gap-2.5 text-sm text-muted">
            <Info aria-hidden className="mt-0.5 size-4 shrink-0" />
            <span>{t("intro.honest")}</span>
          </p>
        </header>

        <section aria-label={open ? t("intro.resume") : t("intro.start")} className="flex flex-col gap-4 border-y border-rule py-6">
          <p className="flex flex-wrap items-baseline gap-x-5 gap-y-1 text-sm">
            <span className="measured text-ink">{t("intro.facts", { count: assessment.question_count })}</span>
            <span>{t("intro.noTimeLimit")}</span>
          </p>
          {open ? (
            <div className="flex flex-col gap-4">
              <div className="flex max-w-sm flex-col gap-1.5">
                <span className="measured text-sm text-ink">{t("intro.resumeProgress", { answered: open.answered, total: open.total })}</span>
                <ProgressTrack value={percent(open.answered, open.total)} />
              </div>
              <Link href={`/assessment/attempts/${open.id}`} className={buttonVariants({ className: "self-start" })}>
                {t("intro.resume")}
                <ArrowRight aria-hidden />
              </Link>
            </div>
          ) : (
            <StartAttemptButton slug={assessment.slug} className="self-start">
              {latest ? t("intro.retake") : t("intro.start")}
              <ArrowRight aria-hidden />
            </StartAttemptButton>
          )}
        </section>

        {latest ? (
          <section aria-labelledby="latest-heading" className="flex flex-col gap-3">
            <h2 id="latest-heading" className="text-[length:var(--text-lg)]">
              {t("intro.latest")}
            </h2>
            <div className="flex flex-wrap items-center gap-x-5 gap-y-3">
              <span className="measured text-[length:var(--text-2xl)] text-ink">{latest.score}%</span>
              <BandChip band={latest.band} />
              <span className="text-sm">{format.dateTime(new Date(latest.submitted_at), { dateStyle: "medium" })}</span>
              <Link href={`/assessment/attempts/${latest.id}/result`} className={buttonVariants({ variant: "secondary", size: "sm" })}>
                {t("intro.viewResult")}
              </Link>
            </div>
          </section>
        ) : null}

        <section aria-labelledby="coverage-heading" className="flex flex-col gap-4">
          <h2 id="coverage-heading" className="text-[length:var(--text-title)]">
            {t("intro.coverage")}
          </h2>
          <ul className="grid gap-px overflow-hidden rounded-card border border-rule bg-rule sm:grid-cols-2">
            {assessment.domains.map((d) => (
              <li key={d.slug} className="flex items-baseline justify-between gap-3 bg-paper-2 px-4 py-3">
                <span className="text-ink">{d.name}</span>
                <span className="measured shrink-0 whitespace-nowrap text-sm">{t("intro.domainQuestions", { count: d.question_count })}</span>
              </li>
            ))}
          </ul>
        </section>

        <History />
      </div>

      <aside className="lg:pt-2">
        <FormulaPanel formula={assessment.formula} className="lg:sticky lg:top-[calc(var(--header-height)+1.5rem)]" />
      </aside>
    </div>
  );
}

function History() {
  const t = useTranslations("assessment");
  const format = useFormatter();
  const history = useAttemptHistory();
  const attempts = history.data?.data ?? [];

  return (
    <section aria-labelledby="history-heading" className="flex flex-col gap-4">
      <h2 id="history-heading" className="text-[length:var(--text-title)]">
        {t("intro.history")}
      </h2>
      {history.isPending ? (
        <div role="status" className="h-16" />
      ) : attempts.length === 0 ? (
        <p className="text-sm">{t("intro.historyEmpty")}</p>
      ) : (
        <ol className="flex flex-col border-t border-rule">
          {attempts.map((a) => (
            <li key={a.id} className="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-rule py-3">
              <span className="text-sm text-ink">{format.dateTime(new Date(a.submitted_at), { dateStyle: "medium", timeStyle: "short" })}</span>
              <span className="measured text-ink">{t("intro.historyScore", { score: a.score })}</span>
              <BandChip band={a.band} />
              <Link
                href={`/assessment/attempts/${a.id}/result`}
                className="ml-auto text-sm text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent"
              >
                {t("intro.viewResult")}
              </Link>
            </li>
          ))}
        </ol>
      )}
    </section>
  );
}
