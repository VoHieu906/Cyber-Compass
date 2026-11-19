"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { PasswordField } from "@/components/ui/input";
import { applyServerErrors } from "@/lib/forms";
import { resetPassword } from "../api";
import { resetPasswordSchema } from "../schemas";
import { useErrorMessage } from "../use-error-message";

type Values = { password: string; password_confirmation: string };

export function ResetPasswordForm({ token, email }: { token: string; email: string }) {
  const t = useTranslations("auth");
  const tv = useTranslations("validation");
  const errorMessage = useErrorMessage();
  const [done, setDone] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const form = useForm<Values>({
    resolver: zodResolver(resetPasswordSchema(tv)),
    defaultValues: { password: "", password_confirmation: "" },
  });
  const { errors } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      await resetPassword({ token, email, ...values });
      setDone(true);
    } catch (error) {
      // An invalid or expired token is reported on `email`, which has no field here: show it as a form alert.
      if (!applyServerErrors(error, form.setError, ["password", "password_confirmation"])) {
        setFormError(errorMessage(error));
      }
    }
  });

  if (done) {
    return (
      <div className="flex flex-col gap-5">
        <Alert tone="success">{t("resetDone")}</Alert>
        <Link href="/login" className={buttonVariants({ className: "w-full" })}>
          {t("loginLink")}
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {formError ? (
        <Alert tone="error">
          <p>{formError}</p>
          <Link href="/forgot-password" className="mt-1 inline-block text-ink underline underline-offset-4">
            {t("requestNewLink")}
          </Link>
        </Alert>
      ) : null}
      {/* Lets password managers associate the new password with the right account. */}
      <input type="email" name="username" autoComplete="username" value={email} readOnly hidden />
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
        {t("resetSubmit")}
      </Button>
    </form>
  );
}
