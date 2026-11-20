"use client";

import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useState, type FormEvent } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, SelectField } from "@/components/ui/input";
import { SegmentedControl } from "@/components/ui/segmented-control";
import type { LearningGoal, SelfLevel, User } from "@/features/auth/types";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { fetchCareers } from "@/features/careers/api";
import { useDomains } from "@/features/catalog/hooks";
import { ApiError } from "@/lib/api/client";
import { useUpdateLearningGoal } from "../hooks";

const LEVELS: SelfLevel[] = ["beginner", "intermediate", "advanced"];
const MIN_HOURS = 0.5;
const MAX_HOURS = 40;

type Values = { career: string; self_level: SelfLevel | undefined; hours: string; domains: string[] };

function toValues(goal: LearningGoal | null): Values {
  return {
    career: goal?.career?.slug ?? "",
    self_level: goal?.self_level ?? undefined,
    hours: goal?.weekly_minutes ? String(goal.weekly_minutes / 60) : "",
    domains: goal?.domains ?? [],
  };
}

/**
 * The learner's goal. The career shapes the roadmap; the weekly time gives it an estimate;
 * areas of interest order the assessment's next steps. The self-rating is only shown beside the
 * measured result, never used for scoring.
 */
export function LearningGoalForm({ user }: { user: User }) {
  const t = useTranslations("settings.goals");
  const tl = useTranslations("difficulty");
  const errorMessage = useErrorMessage();
  const careers = useQuery({ queryKey: ["careers"], queryFn: ({ signal }) => fetchCareers(signal), staleTime: 10 * 60_000 });
  const domains = useDomains();
  const update = useUpdateLearningGoal();
  const [values, setValues] = useState<Values>(() => toValues(user.learning_goal));
  const [errors, setErrors] = useState<Partial<Record<"career" | "self_level" | "weekly_minutes" | "domains", string>>>({});
  const [message, setMessage] = useState<{ tone: "error" | "success"; text: string } | null>(null);
  const careerChanged = values.career !== (user.learning_goal?.career?.slug ?? "");

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setMessage(null);
    const hours = values.hours.trim() === "" ? null : Number(values.hours);
    if (hours !== null && (!Number.isFinite(hours) || hours < MIN_HOURS || hours > MAX_HOURS)) {
      setErrors({ weekly_minutes: t("hoursRange", { min: MIN_HOURS, max: MAX_HOURS }) });
      return;
    }
    setErrors({});
    try {
      const updated = await update.mutateAsync({
        career: values.career || null,
        self_level: values.self_level ?? null,
        weekly_minutes: hours === null ? null : Math.round(hours * 60),
        domains: values.domains,
      });
      setValues(toValues(updated.learning_goal));
      setMessage({ tone: "success", text: careerChanged ? t("savedCareer") : t("saved") });
    } catch (error) {
      if (error instanceof ApiError && error.isValidation) {
        const first = (key: string) => Object.entries(error.fieldErrors).find(([k]) => k === key || k.startsWith(`${key}.`))?.[1][0];
        setErrors({ career: first("career"), self_level: first("self_level"), weekly_minutes: first("weekly_minutes"), domains: first("domains") });
      } else {
        setMessage({ tone: "error", text: errorMessage(error) });
      }
    }
  }

  const toggleDomain = (slug: string) =>
    setValues((v) => ({ ...v, domains: v.domains.includes(slug) ? v.domains.filter((d) => d !== slug) : [...v.domains, slug] }));

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-6">
      <SelectField
        label={t("career")}
        placeholder={t("careerNone")}
        hint={t("careerHint")}
        options={(careers.data ?? []).map((c) => ({ value: c.slug, label: c.title }))}
        value={values.career}
        onChange={(e) => setValues((v) => ({ ...v, career: e.target.value }))}
        error={errors.career}
      />

      <div className="flex flex-col gap-2">
        <span aria-hidden className="text-sm font-medium text-ink">
          {t("selfLevel")}
        </span>
        <SegmentedControl
          label={t("selfLevel")}
          options={LEVELS.map((value) => ({ value, label: tl(value) }))}
          value={values.self_level}
          onChange={(self_level) => setValues((v) => ({ ...v, self_level }))}
          className="self-start"
        />
        <p className="text-sm">{t("selfLevelHint")}</p>
        {values.self_level ? (
          <Button variant="ghost" size="sm" className="self-start" onClick={() => setValues((v) => ({ ...v, self_level: undefined }))}>
            {t("clearLevel")}
          </Button>
        ) : null}
      </div>

      <Field
        label={t("weekly")}
        type="number"
        inputMode="decimal"
        min={MIN_HOURS}
        max={MAX_HOURS}
        step={0.5}
        hint={t("weeklyHint")}
        value={values.hours}
        onChange={(e) => setValues((v) => ({ ...v, hours: e.target.value }))}
        error={errors.weekly_minutes}
        className="max-w-40"
      />

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-1 text-sm font-medium text-ink">{t("interests")}</legend>
        <p className="text-sm">{t("interestsHint")}</p>
        <div className="grid gap-2.5 sm:grid-cols-2">
          {(domains.data ?? []).map((d) => (
            <Checkbox key={d.slug} label={d.name} checked={values.domains.includes(d.slug)} onChange={() => toggleDomain(d.slug)} />
          ))}
        </div>
        {errors.domains ? <p className="text-sm text-bad">{errors.domains}</p> : null}
      </fieldset>

      {message ? (
        <Alert tone={message.tone}>
          {message.text}{" "}
          {message.tone === "success" ? (
            <Link href="/roadmap" className="text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent">
              {t("openRoadmap")}
            </Link>
          ) : null}
        </Alert>
      ) : null}
      <div>
        <Button type="submit" loading={update.isPending}>
          {t("save")}
        </Button>
      </div>
    </form>
  );
}
