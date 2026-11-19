"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Field, SelectField } from "@/components/ui/input";
import type { EducationLevel, User } from "@/features/auth/types";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { applyServerErrors } from "@/lib/forms";
import { useUpdateProfile } from "../hooks";
import { profileSchema, type ProfileFormValues } from "../schemas";

const LEVELS = ["high_school", "undergraduate", "graduate", "other"] as const;
const FIELDS = ["name", "phone", "date_of_birth", "education_level"] as const;

function toValues(user: User): ProfileFormValues {
  return {
    name: user.name,
    phone: user.profile.phone ?? "",
    date_of_birth: user.profile.date_of_birth ?? "",
    education_level: user.profile.education_level ?? "",
  };
}

export function ProfileForm({ user }: { user: User }) {
  const t = useTranslations("settings.profile");
  const tv = useTranslations("validation");
  const tc = useTranslations("common");
  const errorMessage = useErrorMessage();
  const update = useUpdateProfile();
  const [message, setMessage] = useState<{ tone: "error" | "success"; text: string } | null>(null);

  const form = useForm<ProfileFormValues>({ resolver: zodResolver(profileSchema(tv)), defaultValues: toValues(user) });
  const { errors, isDirty, isSubmitting } = form.formState;
  const optional = (label: string) => `${label} (${tc("optional")})`;

  const onSubmit = form.handleSubmit(async (values) => {
    setMessage(null);
    try {
      const updated = await update.mutateAsync({
        name: values.name,
        phone: values.phone || null,
        date_of_birth: values.date_of_birth || null,
        education_level: (values.education_level || null) as EducationLevel | null,
      });
      form.reset(toValues(updated));
      setMessage({ tone: "success", text: t("saved") });
    } catch (error) {
      if (!applyServerErrors(error, form.setError, FIELDS)) setMessage({ tone: "error", text: errorMessage(error) });
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label={t("name")} autoComplete="name" error={errors.name?.message} {...form.register("name")} />
        <Field label={t("email")} type="email" value={user.email} readOnly hint={t("emailHint")} />
        <Field
          label={optional(t("phone"))}
          type="tel"
          autoComplete="tel"
          inputMode="tel"
          error={errors.phone?.message}
          {...form.register("phone")}
        />
        <Field
          label={optional(t("dob"))}
          type="date"
          autoComplete="bday"
          max={new Date().toISOString().slice(0, 10)}
          error={errors.date_of_birth?.message}
          {...form.register("date_of_birth")}
        />
        <SelectField
          label={optional(t("education"))}
          placeholder={t("educationNone")}
          options={LEVELS.map((value) => ({ value, label: t(`levels.${value}`) }))}
          error={errors.education_level?.message}
          {...form.register("education_level")}
        />
      </div>
      {message ? <Alert tone={message.tone}>{message.text}</Alert> : null}
      <div>
        <Button type="submit" loading={isSubmitting} disabled={!isDirty}>
          {t("save")}
        </Button>
      </div>
    </form>
  );
}
