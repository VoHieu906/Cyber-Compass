"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Field, PasswordField } from "@/components/ui/input";
import { applyServerErrors } from "@/lib/forms";
import { useRegister } from "../hooks";
import { safeNext } from "../redirect";
import { registerSchema } from "../schemas";
import type { RegisterInput } from "../types";
import { useErrorMessage } from "../use-error-message";

const FIELDS = ["name", "email", "password", "password_confirmation"] as const;

export function RegisterForm() {
  const t = useTranslations("auth");
  const tv = useTranslations("validation");
  const router = useRouter();
  const next = safeNext(useSearchParams().get("next"));
  const register = useRegister();
  const errorMessage = useErrorMessage();
  const [formError, setFormError] = useState<string | null>(null);

  const form = useForm<RegisterInput>({
    resolver: zodResolver(registerSchema(tv)),
    defaultValues: { name: "", email: "", password: "", password_confirmation: "" },
  });
  const { errors } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      await register.mutateAsync(values);
      router.replace(next);
    } catch (error) {
      if (!applyServerErrors(error, form.setError, FIELDS)) setFormError(errorMessage(error));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {formError ? <Alert tone="error">{formError}</Alert> : null}
      <Field label={t("name")} autoComplete="name" error={errors.name?.message} {...form.register("name")} />
      <Field
        label={t("email")}
        type="email"
        autoComplete="email"
        inputMode="email"
        error={errors.email?.message}
        {...form.register("email")}
      />
      <PasswordField
        label={t("password")}
        autoComplete="new-password"
        hint={t("passwordHint")}
        showLabel={t("showPassword")}
        hideLabel={t("hidePassword")}
        error={errors.password?.message}
        {...form.register("password")}
      />
      <PasswordField
        label={t("passwordConfirm")}
        autoComplete="new-password"
        showLabel={t("showPassword")}
        hideLabel={t("hidePassword")}
        error={errors.password_confirmation?.message}
        {...form.register("password_confirmation")}
      />
      <Button type="submit" loading={form.formState.isSubmitting} className="w-full">
        {t("registerSubmit")}
      </Button>
    </form>
  );
}
