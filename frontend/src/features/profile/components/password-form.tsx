"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { PasswordField } from "@/components/ui/input";
import { changePasswordSchema } from "@/features/auth/schemas";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { applyServerErrors } from "@/lib/forms";
import type { PasswordInput } from "../api";
import { useChangePassword } from "../hooks";

const FIELDS = ["current_password", "password", "password_confirmation"] as const;
const EMPTY: PasswordInput = { current_password: "", password: "", password_confirmation: "" };

export function PasswordForm() {
  const t = useTranslations("settings.security");
  const ta = useTranslations("auth");
  const tv = useTranslations("validation");
  const errorMessage = useErrorMessage();
  const change = useChangePassword();
  const [message, setMessage] = useState<{ tone: "error" | "success"; text: string } | null>(null);

  const form = useForm<PasswordInput>({ resolver: zodResolver(changePasswordSchema(tv)), defaultValues: EMPTY });
  const { errors, isSubmitting } = form.formState;
  const toggle = { showLabel: ta("showPassword"), hideLabel: ta("hidePassword") };

  const onSubmit = form.handleSubmit(async (values) => {
    setMessage(null);
    try {
      await change.mutateAsync(values);
      form.reset(EMPTY);
      setMessage({ tone: "success", text: t("done") });
    } catch (error) {
      if (!applyServerErrors(error, form.setError, FIELDS)) setMessage({ tone: "error", text: errorMessage(error) });
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="flex max-w-[26rem] flex-col gap-5">
      <PasswordField
        label={t("current")}
        autoComplete="current-password"
        error={errors.current_password?.message}
        {...toggle}
        {...form.register("current_password")}
      />
      <PasswordField
        label={t("new")}
        autoComplete="new-password"
        hint={ta("passwordHint")}
        error={errors.password?.message}
        {...toggle}
        {...form.register("password")}
      />
      <PasswordField
        label={t("confirm")}
        autoComplete="new-password"
        error={errors.password_confirmation?.message}
        {...toggle}
        {...form.register("password_confirmation")}
      />
      {message ? <Alert tone={message.tone}>{message.text}</Alert> : null}
      <div>
        <Button type="submit" loading={isSubmitting}>
          {t("submit")}
        </Button>
      </div>
    </form>
  );
}
