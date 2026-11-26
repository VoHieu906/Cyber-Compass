"use client";

import { CircleAlert, CircleCheck, Plus, Play, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { entryStatus, messagesAt, parseJson, toJsonText } from "../state";
import type { CheckReport, LabSolutions, PathMessages, Solution } from "../types";
import { Field, PathList, useValidity, type ValidityReporter } from "./parts";

type Kind = "solutions" | "decoys";

/**
 * Reference solutions and decoys (D78): the actions of a learner who completes the lab, and of one
 * who takes a tempting wrong path. "Run solutions" replays them on the server. They are answer keys:
 * admin only, never sent to learners, and never written to the console or to an error report.
 */
export function SolutionsPanel({
  solutions,
  errors,
  report,
  checking,
  onChange,
  onValidity,
  onRun,
}: {
  solutions: LabSolutions;
  errors: PathMessages;
  report: CheckReport | null;
  checking: boolean;
  onChange: (next: LabSolutions) => void;
  onValidity: ValidityReporter;
  onRun: () => void;
}) {
  const t = useTranslations("labAdmin.solutions");
  // What the last run found; before a run, the problems a save reported.
  const found = report?.errors ?? errors;
  const edit = (kind: Kind, i: number, next: Solution) =>
    onChange({ ...solutions, [kind]: solutions[kind].map((s, j) => (j === i ? next : s)) });
  const remove = (kind: Kind, i: number) => onChange({ ...solutions, [kind]: solutions[kind].filter((_, j) => j !== i) });
  const add = (kind: Kind) =>
    onChange({ ...solutions, [kind]: [...solutions[kind], { name: t(kind === "solutions" ? "newSolution" : "newDecoy"), actions: [] }] });

  return (
    <div className="flex flex-col gap-6">
      <Alert tone="info">{t("secret")}</Alert>
      <div className="flex flex-wrap items-center gap-3">
        <Button onClick={onRun} loading={checking}>
          <Play aria-hidden />
          {t("run")}
        </Button>
        {report ? (
          <p role="status" className="flex items-center gap-2 text-sm text-ink">
            {report.valid ? <CircleCheck aria-hidden className="size-4 text-ok" /> : <CircleAlert aria-hidden className="size-4 text-bad" />}
            {report.valid ? t("allPass") : t("someFail")}
          </p>
        ) : null}
      </div>
      {(["solutions", "decoys"] as const).map((kind) => (
        <section key={kind} aria-labelledby={`${kind}-heading`} className="flex flex-col gap-4">
          <div className="flex flex-col gap-1">
            <h2 id={`${kind}-heading`} className="text-[length:var(--text-title)]">
              {t(`${kind}Title`)}
            </h2>
            <p className="max-w-[44rem] text-sm">{t(`${kind}Intro`)}</p>
          </div>
          {solutions[kind].length === 0 ? <p className="text-sm">{t(`${kind}Empty`)}</p> : null}
          <ul className="flex flex-col gap-4">
            {solutions[kind].map((s, i) => (
              <li key={i} className="panel flex flex-col gap-4 p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="flex items-center gap-2 text-sm font-medium text-ink">
                    {report ? (
                      entryStatus(found, kind, i) === "pass" ? (
                        <CircleCheck aria-hidden className="size-4 text-ok" />
                      ) : (
                        <CircleAlert aria-hidden className="size-4 text-bad" />
                      )
                    ) : null}
                    {report ? (
                      <span className="sr-only">{entryStatus(found, kind, i) === "pass" ? t("passes") : t("fails")}: </span>
                    ) : null}
                    {t(`${kind}Number`, { number: i + 1 })}
                  </p>
                  <Button variant="ghost" size="sm" onClick={() => remove(kind, i)} aria-label={t("remove", { number: i + 1 })}>
                    <Trash2 aria-hidden />
                  </Button>
                </div>
                <Field label={t("name")} messages={found[`solutions.${kind}.${i}.name`]}>
                  {({ id, invalid }) => (
                    <input id={id} value={s.name} maxLength={100} onChange={(e) => edit(kind, i, { ...s, name: e.target.value })} className={controlClasses(invalid)} />
                  )}
                </Field>
                <ActionsField solution={s} label={t("actions")} hint={t("actionsHint")} onChange={(next) => edit(kind, i, next)} onValidity={onValidity} />
                <PathList messages={messagesAt(found, `solutions.${kind}.${i}`)} label={t("problems")} />
              </li>
            ))}
          </ul>
          <Button variant="secondary" className="self-start" onClick={() => add(kind)}>
            <Plus aria-hidden />
            {t(`${kind}Add`)}
          </Button>
        </section>
      ))}
      <PathList messages={report?.warnings ?? {}} tone="warning" label={t("warnings")} />
      <PathList messages={messagesAt(found, "solutions.solutions")} label={t("problems")} />
    </div>
  );
}

/** One solution's actions as a JSON list: exactly what a learner's browser sends. */
function ActionsField({
  solution,
  label,
  hint,
  onChange,
  onValidity,
}: {
  solution: Solution;
  label: string;
  hint: string;
  onChange: (s: Solution) => void;
  onValidity: ValidityReporter;
}) {
  const t = useTranslations("labAdmin.solutions");
  const id = useId();
  const reportValidity = useValidity(onValidity);
  const outside = toJsonText(solution.actions);
  const [text, setText] = useState(outside);
  const [seen, setSeen] = useState(outside);
  const [error, setError] = useState<string | null>(null);
  if (outside !== seen) {
    setSeen(outside);
    if (!error) setText(outside);
  }
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {label}
      </label>
      <textarea
        id={id}
        value={text}
        rows={Math.min(20, Math.max(4, text.split("\n").length + 1))}
        spellCheck={false}
        autoComplete="off"
        aria-invalid={error ? true : undefined}
        aria-describedby={`${id}-note`}
        onChange={(e) => {
          setText(e.target.value);
          const parsed = parseJson(e.target.value);
          if (parsed.ok && Array.isArray(parsed.value)) {
            setError(null);
            reportValidity(false);
            setSeen(toJsonText(parsed.value));
            onChange({ ...solution, actions: parsed.value as Record<string, unknown>[] });
          } else {
            setError(parsed.ok ? t("notList") : parsed.error);
            reportValidity(true);
          }
        }}
        className={controlClasses(Boolean(error), "h-auto py-2 font-mono text-sm")}
      />
      {error ? (
        <p id={`${id}-note`} role="alert" className="text-sm text-bad">
          {t("parseError", { message: error })}
        </p>
      ) : (
        <p id={`${id}-note`} className="text-xs text-muted">
          {hint}
        </p>
      )}
    </div>
  );
}
