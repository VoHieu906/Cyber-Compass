"use client";

import { ArrowLeft, ArrowRight, CircleAlert, CircleMinus, CircleX } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { Alert } from "@/components/ui/alert";
import { buttonVariants } from "@/components/ui/button";
import { renderInline } from "@/features/learning/components/prose";
import { ApiError } from "@/lib/api/client";
import { cn } from "@/lib/utils";
import { useAttemptResult } from "../hooks";
import type { AttemptResult, ReviewItem } from "../types";
import { BandChip, FormulaPanel, QuestionCode, ScoreBar, StartAttemptButton } from "./parts";

export function ResultView({ id }: { id: number }) {
  const t = useTranslations("assessment.result");
  const router = useRouter();
  const result = useAttemptResult(id);

  // An open attempt has no result yet (409): send the learner back to it.
  const open = result.error instanceof ApiError && result.error.status === 409;
  useEffect(() => {
    if (open) router.replace(`/assessment/attempts/${id}`);
  }, [open, router, id]);

  if (result.isError && !open) {
    const missing = result.error instanceof ApiError && result.error.status === 404;
    return (
      <div className="container-page flex max-w-[44rem] flex-col items-start gap-3 py-[var(--space-2xl)]">
        <Alert tone="error">{missing ? t("notFound") : t("loadError")}</Alert>
        <Link href="/assessment" className={buttonVariants({ variant: "secondary" })}>
          <ArrowLeft aria-hidden />
          {t("overview")}
        </Link>
      </div>
    );
  }
  if (!result.data) return <div role="status" className="min-h-[60vh]" />;

  return <Result result={result.data} />;
}

function Result({ result }: { result: AttemptResult }) {
  const t = useTranslations("assessment");
  const tc = useTranslations("common");
  const format = useFormatter();
  const names = new Map(result.domains.map((d) => [d.domain.slug, d.domain.name]));
  const listOf = (slugs: string[]) => (slugs.length ? slugs.map((s) => names.get(s) ?? s).join(", ") : t("result.none"));
  const missed = result.review.filter((r) => !r.is_correct);

  return (
    <div className="container-page flex flex-col gap-12 py-[var(--space-xl)]">
      <header className="flex flex-col gap-5">
        <Breadcrumb label={tc("breadcrumb")} items={[{ href: "/assessment", label: t("title") }, { label: t("result.title") }]} />
        <h1 className="text-[length:var(--text-display-s)]">{t("result.title")}</h1>
        <p className="text-sm">{t("result.submitted", { date: format.dateTime(new Date(result.submitted_at), { dateStyle: "long", timeStyle: "short" }) })}</p>
      </header>

      <section aria-label={t("result.overall")} className="grid gap-px overflow-hidden rounded-card border border-rule bg-rule md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <div className="flex flex-col gap-3 bg-paper-2 p-6">
          <p className="text-sm text-ink">{t("result.overall")}</p>
          <p className="measured text-[length:var(--text-display-s)] leading-none text-ink">{result.overall.score}%</p>
          <BandChip band={result.overall.band} className="self-start" />
          <div className="flex flex-col gap-0.5 text-sm">
            <span className="measured">{t("result.points", { earned: result.overall.earned, possible: result.overall.possible })}</span>
            <span className="measured">{t("result.answered", { answered: result.overall.answered, total: result.overall.total })}</span>
          </div>
        </div>
        <dl className="grid content-start gap-5 bg-paper-2 p-6 text-sm">
          <div className="flex flex-col gap-1">
            <dt className="font-medium text-ink">{t("result.strengths")}</dt>
            <dd>{listOf(result.strengths)}</dd>
          </div>
          <div className="flex flex-col gap-1">
            <dt className="font-medium text-ink">{t("result.gaps")}</dt>
            <dd>{listOf(result.gaps)}</dd>
          </div>
        </dl>
      </section>

      <div className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:gap-[var(--gap-columns)]">
        <div className="flex min-w-0 flex-col gap-12">
          <section aria-labelledby="domains-heading" className="flex flex-col gap-4">
            <h2 id="domains-heading" className="text-[length:var(--text-title)]">
              {t("result.domains")}
            </h2>
            <table className="w-full border-collapse text-sm">
              <thead>
                <tr className="border-b border-rule text-left text-ink">
                  <th scope="col" className="py-2 pr-3 font-medium">
                    {t("result.domainCol")}
                  </th>
                  <th scope="col" className="py-2 pr-3 text-right font-medium">
                    {t("result.scoreCol")}
                  </th>
                  <th scope="col" className="py-2 pr-3 font-medium max-sm:sr-only">
                    {t("result.pointsCol")}
                  </th>
                  <th scope="col" className="py-2 font-medium">
                    {t("result.bandCol")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {result.domains.map((d) => (
                  <tr key={d.domain.slug} className="border-b border-rule align-middle">
                    <th scope="row" className="py-3 pr-3 text-left font-normal">
                      <span className="flex flex-col gap-2">
                        <span className="text-ink">{d.domain.name}</span>
                        <ScoreBar score={d.score} band={d.band} className="max-w-48" />
                      </span>
                    </th>
                    <td className="measured py-3 pr-3 text-right text-ink">{d.score}%</td>
                    <td className="measured py-3 pr-3 max-sm:hidden">
                      {d.earned}/{d.possible}
                    </td>
                    <td className="py-3">
                      <BandChip band={d.band} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          <section aria-labelledby="next-heading" className="flex flex-col gap-4">
            <h2 id="next-heading" className="text-[length:var(--text-title)]">
              {t("result.nextSteps")}
            </h2>
            {result.next_steps.length ? (
              <ol className="flex flex-col border-t border-rule">
                {result.next_steps.map((step) => (
                  <li key={step.domain.slug} className="flex flex-wrap items-center justify-between gap-x-5 gap-y-2 border-b border-rule py-4">
                    <span className="flex items-start gap-2.5 text-ink">
                      <CircleAlert aria-hidden className={cn("mt-0.5 size-4 shrink-0", step.reason === "gap" ? "text-attn" : "text-muted")} />
                      {t(step.reason === "gap" ? "result.nextGap" : "result.nextDeveloping", { domain: step.domain.name, score: step.score })}
                    </span>
                    <Link
                      href={`/courses/${step.course.slug}`}
                      className="inline-flex items-center gap-1.5 text-sm text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent"
                    >
                      {t("result.startCourse", { course: step.course.title })}
                      <ArrowRight aria-hidden className="size-3.5" />
                    </Link>
                  </li>
                ))}
              </ol>
            ) : (
              <p>{t("result.allStrong")}</p>
            )}
            <div className="flex flex-wrap items-start gap-3 pt-2">
              <Link href="/careers" className={buttonVariants()}>
                {t("result.chooseCareer")}
                <ArrowRight aria-hidden />
              </Link>
              <StartAttemptButton slug={result.assessment.slug} variant="secondary">
                {t("result.retake")}
              </StartAttemptButton>
            </div>
          </section>

          <section aria-labelledby="review-heading" className="flex flex-col gap-4">
            <h2 id="review-heading" className="text-[length:var(--text-title)]">
              {t("result.review")}
            </h2>
            <p>
              {missed.length === 0
                ? t("result.allCorrect")
                : t("result.reviewIntro", { correct: result.overall.correct, total: result.overall.total })}
            </p>
            <ol className="flex flex-col gap-4">
              {missed.map((item) => (
                <MissedQuestion key={item.question_id} item={item} domain={names.get(item.domain) ?? item.domain} />
              ))}
            </ol>
          </section>
        </div>

        <aside>
          <FormulaPanel formula={result.formula} className="lg:sticky lg:top-[calc(var(--header-height)+1.5rem)]" />
        </aside>
      </div>
    </div>
  );
}

/** A missed question: the learner's answer, the correct one and why (D26). */
function MissedQuestion({ item, domain }: { item: ReviewItem; domain: string }) {
  const t = useTranslations("assessment.result");
  const unanswered = item.your_option === null;
  return (
    <li className="flex flex-col gap-3 rounded-card border border-rule bg-paper-2 p-5">
      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
        <span className="measured text-ink">{t("question", { position: item.position })}</span>
        <span>{domain}</span>
        <span className="inline-flex items-center gap-1.5 text-ink">
          {unanswered ? <CircleMinus aria-hidden className="size-4 text-muted" /> : <CircleX aria-hidden className="size-4 text-bad" />}
          {unanswered ? t("unanswered") : t("incorrect")}
        </span>
      </div>
      <p className="font-medium text-ink">{renderInline(item.prompt)}</p>
      {item.code ? <QuestionCode code={item.code} /> : null}
      <dl className="flex flex-col gap-2 text-sm">
        {item.your_option ? (
          <div className="rounded-control border border-[color-mix(in_oklab,var(--color-bad)_45%,var(--color-rule))] bg-bad-soft px-3 py-2">
            <dt className="text-muted">{t("yourAnswer")}</dt>
            <dd className="text-ink line-through decoration-bad">{renderInline(item.your_option.label)}</dd>
          </div>
        ) : null}
        {item.correct_option ? (
          <div className="rounded-control border border-[color-mix(in_oklab,var(--color-ok)_45%,var(--color-rule))] bg-ok-soft px-3 py-2">
            <dt className="text-muted">{t("correctAnswer")}</dt>
            <dd className="text-ink">{renderInline(item.correct_option.label)}</dd>
          </div>
        ) : null}
      </dl>
      {item.explanation ? <p className="text-ink-2">{renderInline(item.explanation)}</p> : null}
    </li>
  );
}
