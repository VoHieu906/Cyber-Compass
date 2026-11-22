"use client";

import { ArrowLeft, ArrowRight, Check, CircleAlert } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { ProgressTrack } from "@/features/assessment/components/parts";
import { useTabs } from "@/features/learning/diagrams/use-tabs";
import { useLesson, useTimeOnPage } from "@/features/learning/hooks";
import { renderInline } from "@/features/learning/components/prose";
import type { Lesson } from "@/features/learning/types";
import { ApiError } from "@/lib/api/client";
import { useMediaQuery } from "@/lib/use-media-query";
import { cn } from "@/lib/utils";
import { goTo, useCompassPageItems } from "@/features/compass/page-items";
import { answeredCount, isAnswered, responseFor, toSubmission } from "../answers";
import { useExercise, useExerciseDraft, useSubmitExercise } from "../hooks";
import type { Exercise, ExerciseQuestion, ExerciseResult } from "../types";
import { QuestionInput } from "./question-input";
import { ResultView } from "./result-view";
import { TheoryPanel } from "./theory-panel";

/** Two columns from this width; below it, Exercise and Theory are tabs (master prompt Module F). */
const SPLIT = "(min-width: 64rem)";

export function ExerciseView({ slug }: { slug: string }) {
  const t = useTranslations("exercise");
  const lesson = useLesson(slug);
  const exercise = useExercise(slug);

  const failed = lesson.error ?? exercise.error;
  if (failed) {
    const missing = failed instanceof ApiError && failed.status === 404;
    return (
      <div className="container-page flex max-w-[44rem] flex-col items-start gap-3 py-[var(--space-2xl)]">
        <Alert tone="error">{missing ? t("notFound") : t("loadError")}</Alert>
        <Link href={`/learn/${slug}`} className={buttonVariants({ variant: "secondary" })}>
          <ArrowLeft aria-hidden />
          {t("backToLesson")}
        </Link>
      </div>
    );
  }
  if (!lesson.data || !exercise.data) return <div role="status" aria-label={t("loading")} className="min-h-[60vh]" />;

  return <Workspace lesson={lesson.data} exercise={exercise.data} />;
}

function Workspace({ lesson, exercise }: { lesson: Lesson; exercise: Exercise }) {
  const t = useTranslations();
  const split = useMediaQuery(SPLIT);
  const tabs = useTabs(2);
  const draft = useExerciseDraft(lesson.slug);
  const [index, setIndex] = useState(0);
  const [result, setResult] = useState<ExerciseResult | null>(null);
  const [theory, setTheory] = useState<string | null>(null);
  const questions = exercise.questions;
  const reviewing = index >= questions.length;
  const current = reviewing ? null : questions[index];
  // The theory follows the current question, unless the learner picked a section from the result.
  const sectionId = theory ?? (result ? (result.questions.find((q) => !q.is_correct)?.section?.id ?? null) : (current?.section?.id ?? null));

  function showTheory(id: string) {
    setTheory(id);
    if (!split) tabs.setSelected(1);
    requestAnimationFrame(() => document.getElementById("theory-panel")?.scrollTo?.({ top: 0, behavior: "smooth" }));
  }

  function retry() {
    draft.clear();
    setResult(null);
    setTheory(null);
    setIndex(0);
    tabs.setSelected(0);
  }

  const work = result ? (
    <ResultView result={result} lesson={lesson} onShowTheory={showTheory} onRetry={retry} />
  ) : (
    <Questions
      lesson={lesson}
      exercise={exercise}
      index={index}
      onIndex={(i) => {
        setIndex(i);
        setTheory(null);
      }}
      draft={draft}
      onSubmitted={(r) => {
        draft.clear();
        setResult(r);
        window.scrollTo({ top: 0 });
      }}
    />
  );
  const panel = <TheoryPanel lesson={lesson} sectionId={sectionId} />;

  useCompassPageItems([
    {
      id: "theory",
      label: t("compass.pageTheory"),
      run: () => {
        if (!split) tabs.setSelected(1);
        requestAnimationFrame(() => goTo(document.getElementById("theory-panel")));
      },
    },
    {
      id: "questions",
      label: t("compass.pageQuestions"),
      run: () => {
        if (!split) tabs.setSelected(0);
        requestAnimationFrame(() => goTo(document.querySelector<HTMLElement>(split ? "#exercise-work h2" : "#theory-panel h2")));
      },
    },
  ]);

  return (
    <div className="container-page flex flex-col gap-8 py-[var(--space-xl)]">
      <header className="flex flex-col gap-4">
        <Breadcrumb
          label={t("common.breadcrumb")}
          items={[
            { href: `/courses/${lesson.course.slug}`, label: lesson.course.title },
            { href: `/learn/${lesson.slug}`, label: lesson.title },
            { label: t("exercise.crumb") },
          ]}
        />
        <h1 className="text-[length:var(--text-display-s)]">{t("exercise.title", { lesson: lesson.title })}</h1>
        <p className="flex flex-wrap gap-x-5 gap-y-1 text-sm">
          <span className="measured text-ink">{t("exercise.meta", { count: exercise.question_count, pass: exercise.pass_percent })}</span>
          {exercise.passed ? (
            <span className="inline-flex items-center gap-1.5 text-ink">
              <Check aria-hidden className="size-4 text-accent" />
              {t("exercise.alreadyPassed")}
            </span>
          ) : exercise.best_score !== null ? (
            <span>{t("exercise.best", { score: exercise.best_score })}</span>
          ) : null}
        </p>
      </header>

      {split ? (
        <div className="grid grid-cols-[minmax(0,24rem)_minmax(0,1fr)] gap-[var(--gap-columns)]">
          <aside aria-label={t("exercise.theory.label")}>
            <div id="theory-panel" className="lg:sticky-panel pr-2">
              {panel}
            </div>
          </aside>
          <div id="exercise-work" className="max-w-[46rem] min-w-0">{work}</div>
        </div>
      ) : (
        <div className="flex flex-col gap-5">
          <div {...tabs.listProps} aria-label={t("exercise.tabs")} className="grid grid-cols-2 rounded-control border border-rule bg-paper-2 p-0.5">
            {[t("exercise.tabExercise"), t("exercise.theory.label")].map((label, i) => (
              <button
                key={label}
                type="button"
                {...tabs.tabProps(i)}
                className={cn(
                  "rounded-chip px-3 py-2 text-sm font-medium transition-[background-color,color] duration-[var(--dur-micro)]",
                  tabs.selected === i ? "bg-accent-soft text-ink" : "text-muted hover:text-ink",
                )}
              >
                {label}
              </button>
            ))}
          </div>
          {/* The tab panel keeps the id its tabs point to (aria-controls); the theory id goes inside. */}
          <div {...tabs.panelProps} className="min-w-0 outline-none">
            <div id="theory-panel">{tabs.selected === 0 ? work : panel}</div>
          </div>
        </div>
      )}
    </div>
  );
}

function Questions({
  lesson,
  exercise,
  index,
  onIndex,
  draft,
  onSubmitted,
}: {
  lesson: Lesson;
  exercise: Exercise;
  index: number;
  onIndex: (index: number) => void;
  draft: ReturnType<typeof useExerciseDraft>;
  onSubmitted: (result: ExerciseResult) => void;
}) {
  const t = useTranslations("exercise");
  const questions = exercise.questions;
  const answered = answeredCount(questions, draft.draft);
  const reviewing = index >= questions.length;
  const current = reviewing ? null : questions[index];

  // Move focus to the new question's heading, so keyboard and screen reader users follow along.
  const headingRef = useRef<HTMLHeadingElement>(null);
  const moved = useRef(false);
  useEffect(() => {
    if (moved.current) headingRef.current?.focus();
    moved.current = true;
  }, [index]);

  return (
    <div className="flex flex-col gap-7">
      <div className="flex flex-col gap-3">
        <p className="measured text-sm text-ink">{t("progress", { answered, total: questions.length })}</p>
        <ProgressTrack value={Math.round((answered / questions.length) * 100)} />
        <ol className="flex flex-wrap gap-1.5">
          {questions.map((q, i) => {
            const done = isAnswered(q, draft.draft[q.id]);
            const here = i === index;
            const state = [here ? t("current") : null, done ? t("answered") : t("unanswered")].filter(Boolean).join(", ");
            return (
              <li key={q.id}>
                <button
                  type="button"
                  aria-label={t("jumpTo", { number: i + 1, state })}
                  aria-current={here ? "step" : undefined}
                  onClick={() => onIndex(i)}
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
          <li>
            <Button variant={reviewing ? "secondary" : "ghost"} size="sm" onClick={() => onIndex(questions.length)}>
              {t("toReview")}
            </Button>
          </li>
        </ol>
      </div>

      {current ? (
        <>
          <QuestionCard key={current.id} question={current} index={index} total={questions.length} draft={draft} headingRef={headingRef} />
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-rule pt-6">
            <Button variant="secondary" disabled={index === 0} onClick={() => onIndex(index - 1)}>
              <ArrowLeft aria-hidden />
              {t("previous")}
            </Button>
            <Button onClick={() => onIndex(index + 1)}>
              {index === questions.length - 1 ? t("toReview") : t("next")}
              <ArrowRight aria-hidden />
            </Button>
          </div>
        </>
      ) : (
        <Review
          lesson={lesson}
          exercise={exercise}
          draft={draft}
          headingRef={headingRef}
          onJump={onIndex}
          onSubmitted={onSubmitted}
        />
      )}
    </div>
  );
}

function QuestionCard({
  question,
  index,
  total,
  draft,
  headingRef,
}: {
  question: ExerciseQuestion;
  index: number;
  total: number;
  draft: ReturnType<typeof useExerciseDraft>;
  headingRef: React.RefObject<HTMLHeadingElement | null>;
}) {
  const t = useTranslations("exercise");
  const headingId = useId();
  return (
    <section aria-labelledby={headingId} className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
        <span className="measured text-ink">{t("questionOf", { number: index + 1, total })}</span>
        <span className="rounded-chip border border-rule px-2 py-0.5">{t(`types.${question.type}`)}</span>
      </div>
      {question.type === "scenario" ? (
        <p className="rounded-control border-l-2 border-accent bg-paper-3 px-4 py-3 text-ink">{renderInline(question.payload.scenario)}</p>
      ) : null}
      <h2 id={headingId} ref={headingRef} tabIndex={-1} className="text-[length:var(--text-xl)] leading-snug text-ink outline-none">
        {renderInline(question.prompt)}
      </h2>
      <QuestionInput question={question} response={responseFor(question, draft.draft)} onChange={(r) => draft.answer(question.id, r)} labelledBy={headingId} />
    </section>
  );
}

function Review({
  lesson,
  exercise,
  draft,
  headingRef,
  onJump,
  onSubmitted,
}: {
  lesson: Lesson;
  exercise: Exercise;
  draft: ReturnType<typeof useExerciseDraft>;
  headingRef: React.RefObject<HTMLHeadingElement | null>;
  onJump: (index: number) => void;
  onSubmitted: (result: ExerciseResult) => void;
}) {
  const t = useTranslations("exercise");
  const errorMessage = useErrorMessage();
  const elapsed = useTimeOnPage(`exercise:${lesson.slug}`);
  const submit = useSubmitExercise(lesson.slug, lesson.course.slug);
  const questions = exercise.questions;
  const unanswered = questions.filter((q) => !isAnswered(q, draft.draft[q.id]));

  return (
    <section aria-labelledby="review-heading" className="flex flex-col gap-5">
      <h2 id="review-heading" ref={headingRef} tabIndex={-1} className="text-[length:var(--text-title)] outline-none">
        {t("reviewHeading")}
      </h2>
      <p className="flex gap-2.5 text-ink">
        {unanswered.length ? <CircleAlert aria-hidden className="mt-1 size-4 shrink-0 text-attn" /> : <Check aria-hidden className="mt-1 size-4 shrink-0 text-accent" />}
        <span>{unanswered.length ? t("reviewUnanswered", { count: unanswered.length }) : t("reviewAll")}</span>
      </p>
      {unanswered.length ? (
        <ul className="flex flex-wrap gap-2">
          {unanswered.map((q) => (
            <li key={q.id}>
              <Button variant="secondary" size="sm" onClick={() => onJump(questions.indexOf(q))}>
                {t("questionNumber", { number: questions.indexOf(q) + 1 })}
              </Button>
            </li>
          ))}
        </ul>
      ) : null}
      {submit.isError ? <Alert tone="error">{errorMessage(submit.error) || t("submitError")}</Alert> : null}
      <div className="flex flex-wrap items-center gap-3 border-t border-rule pt-6">
        <Button
          loading={submit.isPending}
          onClick={() => submit.mutate({ answers: toSubmission(questions, draft.draft), seconds: elapsed() }, { onSuccess: onSubmitted })}
        >
          {unanswered.length ? t("submitAnyway") : t("submit")}
        </Button>
        <Button variant="secondary" onClick={() => onJump(questions.length - 1)}>
          <ArrowLeft aria-hidden />
          {t("backToQuestions")}
        </Button>
      </div>
    </section>
  );
}
