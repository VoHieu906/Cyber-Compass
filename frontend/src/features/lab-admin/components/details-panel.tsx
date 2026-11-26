"use client";

import { useTranslations } from "next-intl";
import { controlClasses } from "@/components/ui/input";
import type { DomainInfo, LabDefinition, PathMessages } from "../types";
import { Field } from "./parts";

/** Title, summary, briefing and the facts a learner sees in the list. The engine is fixed once chosen: its scenario is built for it. */
export function DetailsPanel({
  def,
  slug,
  domains,
  errors,
  onChange,
}: {
  def: LabDefinition;
  slug: string;
  domains: DomainInfo[];
  errors: PathMessages;
  onChange: (next: Partial<LabDefinition>) => void;
}) {
  const t = useTranslations("labAdmin.details");
  const te = useTranslations("labs.engines");
  return (
    <div className="panel flex flex-col gap-5 p-5 sm:p-6">
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label={t("slug")} hint={t("slugHint")}>
          {({ id }) => <input id={id} readOnly value={slug} className={controlClasses(false)} />}
        </Field>
        <Field label={t("engine")} hint={t("engineHint")}>
          {({ id }) => <input id={id} readOnly value={te(def.engine)} className={controlClasses(false)} />}
        </Field>
      </div>
      <Field label={t("title")} messages={errors.title}>
        {({ id, invalid, describedBy }) => (
          <input
            id={id}
            value={def.title}
            maxLength={200}
            onChange={(e) => onChange({ title: e.target.value })}
            aria-invalid={invalid || undefined}
            aria-describedby={describedBy}
            className={controlClasses(invalid)}
          />
        )}
      </Field>
      <Field label={t("summary")} hint={t("summaryHint")} messages={errors.summary}>
        {({ id, invalid, describedBy }) => (
          <textarea
            id={id}
            value={def.summary}
            maxLength={500}
            rows={2}
            onChange={(e) => onChange({ summary: e.target.value })}
            aria-invalid={invalid || undefined}
            aria-describedby={describedBy}
            className={controlClasses(invalid, "h-auto py-2")}
          />
        )}
      </Field>
      <Field label={t("briefing")} hint={t("briefingHint")} messages={errors.briefing}>
        {({ id, invalid, describedBy }) => (
          <textarea
            id={id}
            value={def.briefing}
            maxLength={10000}
            rows={10}
            onChange={(e) => onChange({ briefing: e.target.value })}
            aria-invalid={invalid || undefined}
            aria-describedby={describedBy}
            className={controlClasses(invalid, "h-auto py-2 font-mono text-sm")}
          />
        )}
      </Field>
      <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <Field label={t("domain")} messages={errors.domain}>
          {({ id, invalid }) => (
            <select
              id={id}
              value={def.domain}
              onChange={(e) => onChange({ domain: e.target.value })}
              className={controlClasses(invalid)}
            >
              {domains.map((d) => (
                <option key={d.slug} value={d.slug}>
                  {d.name}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field label={t("lesson")} hint={t("lessonHint")} messages={errors.lesson}>
          {({ id, invalid, describedBy }) => (
            <input
              id={id}
              value={def.lesson ?? ""}
              onChange={(e) => onChange({ lesson: e.target.value.trim() === "" ? null : e.target.value.trim() })}
              aria-invalid={invalid || undefined}
              aria-describedby={describedBy}
              spellCheck={false}
              className={controlClasses(invalid)}
            />
          )}
        </Field>
        <Field label={t("difficulty")} messages={errors.difficulty}>
          {({ id, invalid }) => (
            <select
              id={id}
              value={def.difficulty}
              onChange={(e) => onChange({ difficulty: Number(e.target.value) as LabDefinition["difficulty"] })}
              className={controlClasses(invalid)}
            >
              {[1, 2, 3].map((n) => (
                <option key={n} value={n}>
                  {t(`difficulty${n}`)}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field label={t("minutes")} messages={errors.estimated_minutes}>
          {({ id, invalid }) => (
            <input
              id={id}
              type="number"
              min={1}
              max={600}
              value={def.estimated_minutes}
              onChange={(e) => onChange({ estimated_minutes: Number(e.target.value) })}
              className={controlClasses(invalid)}
            />
          )}
        </Field>
      </div>
    </div>
  );
}
