"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/input";
import { applyServerErrors } from "@/lib/forms";
import { requestPasswordReset } from "../api";
import { forgotPasswordSchema } from "../schemas";
import { useErrorMessage } from "../use-error-message";

type Values = { email: string };

export function ForgotPasswordForm() {
  const t = useTranslations("auth");
  const tv = useTranslations("validation");
  const errorMessage = useErrorMessage();
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);

  const form = useForm<Values>({ resolver: zodResolver(forgotPasswordSchema(tv)), defaultValues: { email: "" } });

  const onSubmit = form.handleSubmit(async ({ email }) => {
    setFormError(null);
    try {
      await requestPasswordReset(email);
      setSentTo(email);
    } catch (error) {
      if (!applyServerErrors(error, form.setError, ["email"])) setFormError(errorMessage(error));
    }
  });

  if (sentTo) {
    // The API answers the same for every address, so this message never confirms an account exists.
    return <Alert tone="success">{t("forgotSent", { email: sentTo })}</Alert>;
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {formError ? <Alert tone="error">{formError}</Alert> : null}
      <Field
        label={t("email")}
        type="email"
        autoComplete="email"
        inputMode="email"
        error={form.formState.errors.email?.message}
        {...form.register("email")}
      />
      <Button type="submit" loading={form.formState.isSubmitting} className="w-full">
        {t("forgotSubmit")}
      </Button>
    </form>
  );
}
