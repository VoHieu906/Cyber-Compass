"use client";

import { Check, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useState } from "react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { renderInline } from "../components/prose";
import type { KnowledgeCheck as KnowledgeCheckData } from "./schemas";

type Question = KnowledgeCheckData["questions"][number];

/**
 * Practice questions. Ungraded and local: answers are never sent or stored, and the lesson's
 * progress does not depend on them (PHASE_3_5 A4).
 */
export function KnowledgeCheck({ data }: { data: KnowledgeCheckData }) {
  const t = useTranslations("learn.blocks");
  return (
    <div className="flex flex-col gap-4 rounded-control border border-rule bg-paper-2 p-4 sm:p-5">
      <p className="text-sm text-muted">{t("checkIntro")}</p>
      {data.questions.map((q, i) => (
        <QuestionCard key={i} question={q} number={data.questions.length > 1 ? i + 1 : null} />
      ))}
    </div>
  );
}

function QuestionCard({ question, number }: { question: Question; number: number | null }) {
  const t = useTranslations("learn.blocks");
  const id = useId();
  const multiple = question.options.filter((o) => o.correct).length > 1;
  const [chosen, setChosen] = useState<Set<number>>(new Set());
  const [checked, setChecked] = useState(false);
  const correct = question.options.every((o, i) => o.correct === chosen.has(i));

  function toggle(i: number) {
    setChecked(false);
    setChosen((prev) => {
      if (!multiple) return new Set([i]);
      const next = new Set(prev);
      if (next.has(i)) next.delete(i);
      else next.add(i);
      return next;
    });
  }

  return (
    <fieldset className="flex flex-col gap-3 border-t border-rule pt-4 first-of-type:border-t-0 first-of-type:pt-0">
      <legend className="contents">
        <span className="font-medium text-ink">
          {number ? <span className="measured mr-2 text-muted">{number}.</span> : null}
          {renderInline(question.prompt)}
        </span>
      </legend>
      {multiple ? <p className="text-sm text-muted">{t("chooseAll")}</p> : null}
      <ul className="flex flex-col gap-1.5">
        {question.options.map((o, i) => {
          const on = chosen.has(i);
          const reveal = checked && on;
          return (
            <li key={i}>
              <label
                className={cn(
                  "flex cursor-pointer items-start gap-3 rounded-control border px-3 py-2 text-sm transition-[border-color,background-color] duration-[var(--dur-micro)]",
                  !checked && (on ? "border-accent bg-accent-soft" : "border-rule bg-paper hover:border-rule-2"),
                  reveal && o.correct && "border-ok bg-ok-soft",
                  reveal && !o.correct && "border-bad bg-bad-soft",
                  checked && !reveal && "border-rule bg-paper",
                )}
              >
                <input
                  type={multiple ? "checkbox" : "radio"}
                  name={id}
                  checked={on}
                  onChange={() => toggle(i)}
                  className="mt-0.5 accent-[var(--color-accent)]"
                />
                <span className="flex-1 text-ink">{renderInline(o.text)}</span>
                {reveal ? (
                  o.correct ? (
                    <Check aria-label={t("correctOption")} className="size-4 shrink-0 text-ok" />
                  ) : (
                    <X aria-label={t("wrongOption")} className="size-4 shrink-0 text-bad" />
                  )
                ) : null}
              </label>
            </li>
          );
        })}
      </ul>
      <div className="flex flex-wrap items-center gap-3">
        <Button size="sm" variant="secondary" disabled={chosen.size === 0} onClick={() => setChecked(true)}>
          {t("checkAnswer")}
        </Button>
        {checked ? (
          <Button size="sm" variant="ghost" onClick={() => (setChosen(new Set()), setChecked(false))}>
            {t("tryAgain")}
          </Button>
        ) : null}
      </div>
      <div aria-live="polite">
        {checked ? (
          <div
            className={cn(
              "rounded-control border p-3 text-sm",
              correct ? "border-ok bg-ok-soft" : "border-rule bg-paper-3",
            )}
          >
            <p className="font-medium text-ink">{correct ? t("rightAnswer") : t("notQuite")}</p>
            <p className="mt-1 text-ink">{renderInline(question.explanation)}</p>
          </div>
        ) : null}
      </div>
    </fieldset>
  );
}
