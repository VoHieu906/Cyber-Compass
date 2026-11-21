"use client";

import { ArrowRight, BookOpen, CircleAlert, CircleCheck, CircleX, RotateCcw } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useEffect, useRef } from "react";
import { Button, buttonVariants } from "@/components/ui/button";
import { renderInline } from "@/features/learning/components/prose";
import type { Lesson } from "@/features/learning/types";
import { cn } from "@/lib/utils";
import { describe, type DescribeLabels } from "../answers";
import type { ExerciseResult, ResultQuestion } from "../types";

/**
 * A scored attempt (D31): the score against the pass mark, then every question with its status,
 * the learner's answer, the correct answer when it was wrong, the explanation and a link to the
 * theory that teaches it.
 */
export function ResultView({
  result,
  lesson,
  onShowTheory,
  onRetry,
}: {
  result: ExerciseResult;
  lesson: Lesson;
  onShowTheory: (sectionId: string) => void;
  onRetry: () => void;
}) {
  const t = useTranslations("exercise.result");
  const headingRef = useRef<HTMLHeadingElement>(null);
  useEffect(() => headingRef.current?.focus(), []);
  const next = lesson.navigation.next;

  return (
    <div className="flex flex-col gap-8">
      <section
        aria-labelledby="result-heading"
        className={cn("flex flex-col gap-3 rounded-card border p-5", result.passed ? "border-accent bg-accent-soft" : "border-attn bg-attn-soft")}
      >
        <h2 id="result-heading" ref={headingRef} tabIndex={-1} className="flex items-center gap-2 text-[length:var(--text-title)] outline-none">
          {result.passed ? <CircleCheck aria-hidden className="size-5 text-accent" /> : <CircleAlert aria-hidden className="size-5 text-attn" />}
          {result.passed ? t("passed", { score: result.score }) : t("failed", { score: result.score })}
        </h2>
        <p className="text-ink">
          {t("summary", { correct: result.correct, total: result.total, pass: result.pass_percent })}{" "}
          {result.passed ? t("lessonDone") : t("tryAgainHint")}
        </p>
        <div className="flex flex-wrap gap-3 pt-1">
          {result.passed ? (
            next ? (
              <Link href={`/learn/${next.slug}`} className={buttonVariants()}>
                {t("nextLesson", { title: next.title })}
                <ArrowRight aria-hidden />
              </Link>
            ) : (
              <Link href={`/courses/${lesson.course.slug}`} className={buttonVariants()}>
                {t("backToCourse")}
                <ArrowRight aria-hidden />
              </Link>
            )
          ) : (
            <Button onClick={onRetry}>
              <RotateCcw aria-hidden />
              {t("retry")}
            </Button>
          )}
          <Link href={`/learn/${lesson.slug}`} className={buttonVariants({ variant: "secondary" })}>
            {t("backToLesson")}
          </Link>
          {result.passed ? (
            <Button variant="ghost" onClick={onRetry}>
              {t("retakeAnyway")}
            </Button>
          ) : null}
        </div>
      </section>

      <section aria-labelledby="answers-heading" className="flex flex-col gap-4">
        <h2 id="answers-heading" className="text-[length:var(--text-title)]">
          {t("answers")}
        </h2>
        <ol className="flex flex-col gap-4">
          {result.questions.map((q) => (
            <ResultRow key={q.id} question={q} onShowTheory={onShowTheory} />
          ))}
        </ol>
      </section>
    </div>
  );
}

function ResultRow({ question, onShowTheory }: { question: ResultQuestion; onShowTheory: (sectionId: string) => void }) {
  const t = useTranslations("exercise");
  const labels: DescribeLabels = { true: t("input.true"), false: t("input.false"), empty: t("result.empty"), or: t("result.or") };
  const status = question.is_correct ? "correct" : question.response ? "incorrect" : "unanswered";
  const Icon = question.is_correct ? CircleCheck : CircleX;
  const yours = describe(question, question.response, labels);
  const correct = describe(question, question.correct_answer, labels);

  return (
    <li className={cn("panel flex flex-col gap-3 p-5", !question.is_correct && "border-l-2 border-l-bad")}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="measured text-sm text-muted">{t("questionNumber", { number: question.position })}</span>
        <span className={cn("inline-flex items-center gap-1.5 text-sm font-medium", question.is_correct ? "text-ink" : "text-bad")}>
          <Icon aria-hidden className={cn("size-4", question.is_correct ? "text-accent" : "text-bad")} />
          {t(`result.${status}`)}
        </span>
      </div>
      <p className="font-medium text-ink">{renderInline(question.prompt)}</p>
      <dl className="grid gap-3 text-sm sm:grid-cols-2">
        <div className="flex flex-col gap-1">
          <dt className="text-muted">{t("result.yourAnswer")}</dt>
          <dd className="text-ink">{yours.length ? <Lines lines={yours} ordered={question.type === "ordering"} /> : t("result.noAnswer")}</dd>
        </div>
        {question.correct_answer ? (
          <div className="flex flex-col gap-1">
            <dt className="text-muted">{t("result.correctAnswer")}</dt>
            <dd className="text-ink">
              <Lines lines={correct} ordered={question.type === "ordering"} />
            </dd>
          </div>
        ) : null}
      </dl>
      <p className="text-ink-2">{renderInline(question.explanation)}</p>
      {question.section ? (
        <Button variant="ghost" size="sm" className="self-start" onClick={() => onShowTheory(question.section!.id)}>
          <BookOpen aria-hidden />
          {t("result.showTheory", { heading: question.section.heading ?? t("result.theSection") })}
        </Button>
      ) : null}
    </li>
  );
}

function Lines({ lines, ordered = false }: { lines: string[]; ordered?: boolean }) {
  if (lines.length === 1) return <span>{renderInline(lines[0])}</span>;
  const List = ordered ? "ol" : "ul";
  return (
    <List className={cn("flex flex-col gap-0.5", ordered && "list-decimal pl-5")}>
      {lines.map((line, i) => (
        <li key={i}>{renderInline(line)}</li>
      ))}
    </List>
  );
}
