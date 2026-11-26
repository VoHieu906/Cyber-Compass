"use client";

import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useState } from "react";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { checkScenario } from "../schemas";
import { KIND_TEMPLATES, kindFields, messagesAt, mergeMessages, newCheckpoint, parseJson, toJsonText } from "../state";
import type { CheckpointDef, LabDefinition, PathMessages } from "../types";
import { Field, PathList, useValidity, type ValidityReporter } from "./parts";

/**
 * The tasks a learner must complete (checkpoints). Every kind has an id, a title and an optional
 * hint; an answer takes its accepted texts, one per line, and any other kind takes the engine's own
 * fields as JSON. The kinds on offer come from the engine.
 */
export function CheckpointsPanel({
  def,
  kinds,
  errors,
  onChange,
  onValidity,
}: {
  def: LabDefinition;
  kinds: string[];
  errors: PathMessages;
  onChange: (checkpoints: CheckpointDef[]) => void;
  onValidity: ValidityReporter;
}) {
  const t = useTranslations("labAdmin.checkpoints");
  const list = def.scenario.checkpoints;
  const messages = mergeMessages(messagesAt(errors, "scenario.checkpoints"), messagesAt(checkScenario(def.engine, def.scenario), "scenario.checkpoints"));
  const update = (i: number, next: CheckpointDef) => onChange(list.map((c, j) => (j === i ? next : c)));
  const move = (i: number, by: -1 | 1) => {
    const next = [...list];
    [next[i], next[i + by]] = [next[i + by], next[i]];
    onChange(next);
  };

  return (
    <div className="flex flex-col gap-4">
      <p className="max-w-[44rem] text-sm">{t("intro")}</p>
      <ol className="flex flex-col gap-4">
        {list.map((c, i) => (
          <li key={i} className="panel flex flex-col gap-4 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <h3 className="text-[length:var(--text-base)] font-medium">{t("number", { number: i + 1 })}</h3>
              <div className="flex gap-1">
                <Button variant="ghost" size="sm" disabled={i === 0} onClick={() => move(i, -1)} aria-label={t("moveUp", { number: i + 1 })}>
                  <ArrowUp aria-hidden />
                </Button>
                <Button variant="ghost" size="sm" disabled={i === list.length - 1} onClick={() => move(i, 1)} aria-label={t("moveDown", { number: i + 1 })}>
                  <ArrowDown aria-hidden />
                </Button>
                <Button variant="ghost" size="sm" disabled={list.length === 1} onClick={() => onChange(list.filter((_, j) => j !== i))} aria-label={t("remove", { number: i + 1 })}>
                  <Trash2 aria-hidden />
                </Button>
              </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label={t("id")} hint={t("idHint")} messages={messages[`scenario.checkpoints.${i}.id`]}>
                {({ id, invalid, describedBy }) => (
                  <input id={id} value={c.id} maxLength={60} spellCheck={false} onChange={(e) => update(i, { ...c, id: e.target.value })} aria-invalid={invalid || undefined} aria-describedby={describedBy} className={controlClasses(invalid)} />
                )}
              </Field>
              <Field label={t("kind")} hint={t("kindHint")} messages={messages[`scenario.checkpoints.${i}.kind`]}>
                {({ id, invalid }) => (
                  <select
                    id={id}
                    value={c.kind}
                    onChange={(e) => {
                      const kind = e.target.value;
                      update(i, { id: c.id, title: c.title, ...(c.hint ? { hint: c.hint } : {}), kind, ...(KIND_TEMPLATES[kind] ?? {}) });
                    }}
                    className={controlClasses(invalid)}
                  >
                    {[...new Set([...kinds, c.kind])].map((k) => (
                      <option key={k} value={k}>
                        {t(`kinds.${k}`)}
                      </option>
                    ))}
                  </select>
                )}
              </Field>
            </div>
            <Field label={t("title")} hint={t("titleHint")} messages={messages[`scenario.checkpoints.${i}.title`]}>
              {({ id, invalid, describedBy }) => (
                <textarea id={id} value={c.title} rows={2} maxLength={300} onChange={(e) => update(i, { ...c, title: e.target.value })} aria-invalid={invalid || undefined} aria-describedby={describedBy} className={controlClasses(invalid, "h-auto py-2")} />
              )}
            </Field>
            <Field label={t("hint")} messages={messages[`scenario.checkpoints.${i}.hint`]}>
              {({ id, invalid }) => (
                <input
                  id={id}
                  value={c.hint ?? ""}
                  maxLength={500}
                  onChange={(e) => {
                    const { hint: _hint, ...rest } = c;
                    void _hint;
                    update(i, e.target.value ? { ...rest, hint: e.target.value } : rest);
                  }}
                  className={controlClasses(invalid)}
                />
              )}
            </Field>
            {c.kind === "answer" ? (
              <AnswersField checkpoint={c} number={i + 1} onChange={(next) => update(i, next)} />
            ) : (
              <KindFields checkpoint={c} number={i + 1} onChange={(next) => update(i, next)} onValidity={onValidity} />
            )}
          </li>
        ))}
      </ol>
      <Button variant="secondary" className="self-start" onClick={() => onChange([...list, newCheckpoint(kinds.includes("answer") ? "answer" : kinds[0], list.map((c) => c.id))])}>
        <Plus aria-hidden />
        {t("add")}
      </Button>
      <PathList messages={messages} label={t("problems")} />
    </div>
  );
}

/** Accepted answers, one per line. They are compared ignoring case and extra spaces. */
function AnswersField({ checkpoint, number, onChange }: { checkpoint: CheckpointDef; number: number; onChange: (c: CheckpointDef) => void }) {
  const t = useTranslations("labAdmin.checkpoints");
  const answers = (checkpoint.answers as string[] | undefined) ?? [];
  return (
    <Field label={t("answers", { number })} hint={t("answersHint")}>
      {({ id, describedBy }) => (
        <textarea
          id={id}
          value={answers.join("\n")}
          rows={Math.min(8, Math.max(2, answers.length + 1))}
          spellCheck={false}
          autoComplete="off"
          aria-describedby={describedBy}
          onChange={(e) => onChange({ ...checkpoint, answers: e.target.value.split("\n") })}
          className={controlClasses(false, "h-auto py-2 font-mono text-sm")}
        />
      )}
    </Field>
  );
}

/** The engine's own fields for a kind (a file, a route, test packets) as JSON. */
function KindFields({
  checkpoint,
  number,
  onChange,
  onValidity,
}: {
  checkpoint: CheckpointDef;
  number: number;
  onChange: (c: CheckpointDef) => void;
  onValidity: ValidityReporter;
}) {
  const t = useTranslations("labAdmin.checkpoints");
  const id = useId();
  const reportValidity = useValidity(onValidity);
  const outside = toJsonText(kindFields(checkpoint));
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
        {t("details", { number })}
      </label>
      <textarea
        id={id}
        value={text}
        rows={Math.min(14, Math.max(3, text.split("\n").length + 1))}
        spellCheck={false}
        autoComplete="off"
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        onChange={(e) => {
          setText(e.target.value);
          const parsed = parseJson(e.target.value);
          if (parsed.ok && parsed.value && typeof parsed.value === "object" && !Array.isArray(parsed.value)) {
            setError(null);
            reportValidity(false);
            setSeen(toJsonText(parsed.value));
            const { id: cid, title, hint, kind } = checkpoint;
            onChange({ id: cid, title, ...(hint ? { hint } : {}), kind, ...(parsed.value as Record<string, unknown>) });
          } else {
            setError(parsed.ok ? t("notObject") : parsed.error);
            reportValidity(true);
          }
        }}
        className={controlClasses(Boolean(error), "h-auto py-2 font-mono text-sm")}
      />
      {error ? (
        <p id={`${id}-error`} role="alert" className="text-sm text-bad">
          {t("parseError", { message: error })}
        </p>
      ) : null}
    </div>
  );
}
