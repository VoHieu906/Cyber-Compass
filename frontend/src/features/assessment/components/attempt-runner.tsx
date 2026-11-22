"use client";

import { ArrowLeft, ArrowRight, Check, CircleAlert, LoaderCircle } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useId, useMemo, useRef, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { renderInline } from "@/features/learning/components/prose";
import { ApiError } from "@/lib/api/client";
import { cn } from "@/lib/utils";
import { useAnswerSaver, useAttempt, useSubmitAttempt } from "../hooks";
import { answeredCount, domainProgress, firstUnanswered, percent, selectionsFrom, type DomainProgress, type Selections } from "../progress";
import type { Attempt, AttemptQuestion } from "../types";
import { ProgressTrack, QuestionCode } from "./parts";

const DIFFICULTY = ["beginner", "intermediate", "advanced"] as const;

export function AttemptRunner({ id }: { id: number }) {
  const t = useTranslations("assessment.take");
  const router = useRouter();
  const attempt = useAttempt(id);
  const toResult = useCallback(() => router.replace(`/assessment/attempts/${id}/result`), [router, id]);

  const submitted = attempt.data?.status === "submitted";
  useEffect(() => {
    if (submitted) toResult();
  }, [submitted, toResult]);

  if (attempt.isError) {
    const missing = attempt.error instanceof ApiError && attempt.error.status === 404;
    return (
      <div className="container-page flex max-w-[44rem] flex-col items-start gap-3 py-[var(--space-2xl)]">
        <Alert tone="error">{missing ? t("notFound") : t("loadError")}</Alert>
        <Link href="/assessment" className={buttonVariants({ variant: "secondary" })}>
          <ArrowLeft aria-hidden />
          {t("backToAssessment")}
        </Link>
      </div>
    );
  }
  if (!attempt.data || submitted) return <div role="status" className="min-h-[60vh]" />;

  return <Runner attempt={attempt.data} onSubmitted={toResult} />;
}

function Runner({ attempt, onSubmitted }: { attempt: Attempt; onSubmitted: () => void }) {
  const t = useTranslations("assessment.take");
  const saver = useAnswerSaver(attempt.id, onSubmitted);
  const questions = attempt.questions;
  const selections = useMemo(() => selectionsFrom(questions, saver.local), [questions, saver.local]);
  const [index, setIndex] = useState(() => firstUnanswered(questions, selectionsFrom(questions, {})));
  const reviewing = index >= questions.length;
  const answered = answeredCount(questions, selections);
  const groups = domainProgress(questions, selections);
  const current = reviewing ? null : questions[index];
  const group = current ? groups.find((g) => g.slug === current.domain.slug)! : null;

  // Move focus to the new question's heading, so keyboard and screen reader users follow along.
  const headingRef = useRef<HTMLHeadingElement>(null);
  const moved = useRef(false);
  useEffect(() => {
    if (moved.current) headingRef.current?.focus();
    moved.current = true;
  }, [index]);

  return (
    <div className="container-page grid gap-8 py-[var(--space-xl)] lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] lg:gap-[var(--gap-columns)]">
      <aside className="max-lg:hidden">
        <nav aria-label={t("domains")} className="-m-1 flex flex-col gap-5 p-1 lg:sticky-panel">
          <p className="text-sm font-medium text-ink">{t("domains")}</p>
          <ol className="flex flex-col gap-4">
            {groups.map((g) => (
              <li key={g.slug} className="flex flex-col gap-2">
                <div className="flex items-baseline justify-between gap-2 text-sm">
                  <span className={cn(g.slug === current?.domain.slug ? "text-ink" : "text-muted")}>{g.name}</span>
                  <span className="measured shrink-0 whitespace-nowrap text-xs">{t("domainProgress", { answered: g.answered, total: g.total })}</span>
                </div>
                <QuestionDots group={g} questions={questions} selections={selections} index={index} onJump={setIndex} />
              </li>
            ))}
          </ol>
          <Button variant={reviewing ? "secondary" : "ghost"} size="sm" className="self-start" onClick={() => setIndex(questions.length)}>
            {t("toReview")}
          </Button>
        </nav>
      </aside>

      <div className="flex max-w-[44rem] min-w-0 flex-col gap-8">
        <div className="flex flex-col gap-2">
          <div className="flex flex-wrap items-baseline justify-between gap-x-5 gap-y-1 text-sm">
            <span className="measured text-ink">{t("progress", { answered, total: questions.length })}</span>
            <SaveStatus saving={saver.saving} failed={saver.failed.length} touched={Object.keys(saver.local).length > 0} onRetry={saver.retry} />
          </div>
          <ProgressTrack value={percent(answered, questions.length)} />
        </div>

        {current && group ? (
          <>
            <div className="lg:hidden">
              <div className="mb-2 flex items-center justify-between gap-3">
                <p className="text-sm text-ink">{group.name}</p>
                <Button variant="ghost" size="sm" onClick={() => setIndex(questions.length)}>
                  {t("toReview")}
                </Button>
              </div>
              <QuestionDots group={group} questions={questions} selections={selections} index={index} onJump={setIndex} />
            </div>
            <QuestionView
              key={current.id}
              question={current}
              index={index}
              total={questions.length}
              selected={selections[current.id] ?? null}
              onChoose={(optionId) => saver.choose(current.id, optionId)}
              headingRef={headingRef}
            />
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-rule pt-6">
              <Button variant="secondary" disabled={index === 0} onClick={() => setIndex(index - 1)}>
                <ArrowLeft aria-hidden />
                {t("previous")}
              </Button>
              {index === questions.length - 1 ? (
                <Button onClick={() => setIndex(questions.length)}>
                  {t("toReview")}
                  <ArrowRight aria-hidden />
                </Button>
              ) : (
                <Button onClick={() => setIndex(index + 1)}>
                  {t("next")}
                  <ArrowRight aria-hidden />
                </Button>
              )}
            </div>
          </>
        ) : (
          <ReviewStep
            attemptId={attempt.id}
            unanswered={questions.length - answered}
            blocked={saver.saving || saver.failed.length > 0}
            headingRef={headingRef}
            onFirstUnanswered={() => setIndex(firstUnanswered(questions, selections))}
            onBack={() => setIndex(questions.length - 1)}
            onSubmitted={onSubmitted}
          />
        )}
      </div>
    </div>
  );
}

function QuestionDots({
  group,
  questions,
  selections,
  index,
  onJump,
}: {
  group: DomainProgress;
  questions: AttemptQuestion[];
  selections: Selections;
  index: number;
  onJump: (index: number) => void;
}) {
  const t = useTranslations("assessment.take");
  return (
    <ol className="flex flex-wrap gap-1.5">
      {questions.slice(group.start, group.start + group.total).map((q, k) => {
        const i = group.start + k;
        const done = selections[q.id] != null;
        const here = i === index;
        const state = [here ? t("current") : null, done ? t("answered") : t("unanswered")].filter(Boolean).join(", ");
        return (
          <li key={q.id}>
            <button
              type="button"
              aria-label={t("jumpTo", { position: i + 1, state })}
              aria-current={here ? "step" : undefined}
              onClick={() => onJump(i)}
              className={cn(
                "measured flex size-8 items-center justify-center rounded-chip border text-xs transition-[border-color,background-color] duration-[var(--dur-micro)]",
                done ? "border-rule-2 bg-paper-3 text-ink" : "border-dashed border-rule text-muted hover:border-rule-2",
                here && "border-solid border-accent bg-accent-soft text-ink",
              )}
            >
              {i + 1}
            </button>
          </li>
        );
      })}
    </ol>
  );
}

function QuestionView({
  question,
  index,
  total,
  selected,
  onChoose,
  headingRef,
}: {
  question: AttemptQuestion;
  index: number;
  total: number;
  selected: number | null;
  onChoose: (optionId: number | null) => void;
  headingRef: React.RefObject<HTMLHeadingElement | null>;
}) {
  const t = useTranslations();
  const headingId = useId();
  return (
    <section aria-labelledby={headingId} className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
        <span className="measured text-ink">{t("assessment.take.questionOf", { position: index + 1, total })}</span>
        <span className="rounded-chip border border-rule px-2 py-0.5">{t(`difficulty.${DIFFICULTY[question.difficulty - 1]}`)}</span>
      </div>
      <h1 id={headingId} ref={headingRef} tabIndex={-1} className="text-[length:var(--text-xl)] leading-snug text-ink outline-none">
        {renderInline(question.prompt)}
      </h1>
      {question.code ? <QuestionCode code={question.code} /> : null}
      <fieldset aria-labelledby={headingId} className="flex flex-col gap-2">
        {question.options.map((o) => {
          const on = selected === o.id;
          return (
            <label
              key={o.id}
              className={cn(
                "flex cursor-pointer items-start gap-3 rounded-control border px-4 py-3 transition-[border-color,background-color] duration-[var(--dur-micro)]",
                on ? "border-accent bg-accent-soft" : "border-rule bg-paper-2 hover:border-rule-2",
              )}
            >
              <input
                type="radio"
                name={`question-${question.id}`}
                checked={on}
                onChange={() => onChoose(o.id)}
                className="mt-1 accent-[var(--color-accent)]"
              />
              <span className="flex-1 text-ink">{renderInline(o.label)}</span>
            </label>
          );
        })}
      </fieldset>
      {selected !== null ? (
        <Button variant="ghost" size="sm" className="self-start" onClick={() => onChoose(null)}>
          {t("assessment.take.clear")}
        </Button>
      ) : null}
    </section>
  );
}

function SaveStatus({ saving, failed, touched, onRetry }: { saving: boolean; failed: number; touched: boolean; onRetry: () => void }) {
  const t = useTranslations("assessment.take");
  return (
    <span aria-live="polite" className="flex items-center gap-2">
      {failed > 0 ? (
        <>
          <CircleAlert aria-hidden className="size-4 text-bad" />
          <span className="text-ink">{t("saveFailed", { count: failed })}</span>
          <Button variant="secondary" size="sm" onClick={onRetry} disabled={saving}>
            {t("retry")}
          </Button>
        </>
      ) : saving ? (
        <>
          <LoaderCircle aria-hidden className="size-4 animate-spin" />
          {t("saving")}
        </>
      ) : touched ? (
        <>
          <Check aria-hidden className="size-4 text-accent" />
          {t("saved")}
        </>
      ) : null}
    </span>
  );
}

function ReviewStep({
  attemptId,
  unanswered,
  blocked,
  headingRef,
  onFirstUnanswered,
  onBack,
  onSubmitted,
}: {
  attemptId: number;
  unanswered: number;
  blocked: boolean;
  headingRef: React.RefObject<HTMLHeadingElement | null>;
  onFirstUnanswered: () => void;
  onBack: () => void;
  onSubmitted: () => void;
}) {
  const t = useTranslations("assessment.take");
  const submit = useSubmitAttempt(attemptId);
  const errorMessage = useErrorMessage();

  function onSubmit() {
    submit.mutate(undefined, {
      onSuccess: onSubmitted,
      onError: (error) => {
        if (error instanceof ApiError && error.status === 409) onSubmitted();
      },
    });
  }

  return (
    <section aria-labelledby="review-heading" className="flex flex-col gap-5">
      <h1 id="review-heading" ref={headingRef} tabIndex={-1} className="text-[length:var(--text-title)] outline-none">
        {t("reviewHeading")}
      </h1>
      <p className={cn("flex gap-2.5", unanswered > 0 ? "text-ink" : "text-ink-2")}>
        {unanswered > 0 ? <CircleAlert aria-hidden className="mt-1 size-4 shrink-0 text-attn" /> : <Check aria-hidden className="mt-1 size-4 shrink-0 text-accent" />}
        <span>{unanswered > 0 ? t("reviewUnanswered", { count: unanswered }) : t("reviewAll")}</span>
      </p>
      {submit.isError && !(submit.error instanceof ApiError && submit.error.status === 409) ? (
        <Alert tone="error">{errorMessage(submit.error) || t("submitError")}</Alert>
      ) : null}
      <div className="flex flex-wrap items-center gap-3 border-t border-rule pt-6">
        <Button onClick={onSubmit} loading={submit.isPending} disabled={blocked}>
          {unanswered > 0 ? t("submitAnyway") : t("submit")}
        </Button>
        {unanswered > 0 ? (
          <Button variant="secondary" onClick={onFirstUnanswered}>
            {t("firstUnanswered")}
          </Button>
        ) : null}
        <Button variant="ghost" onClick={onBack}>
          <ArrowLeft aria-hidden />
          {t("back")}
        </Button>
      </div>
      {blocked ? <p className="text-sm">{t("waitForSave")}</p> : null}
    </section>
  );
}
