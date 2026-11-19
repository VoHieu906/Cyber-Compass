"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, PasswordField } from "@/components/ui/input";
import { useLogin } from "../hooks";
import { safeNext } from "../redirect";
import { loginSchema } from "../schemas";
import type { LoginInput } from "../types";
import { useErrorMessage } from "../use-error-message";

export function LoginForm() {
  const t = useTranslations("auth");
  const tv = useTranslations("validation");
  const router = useRouter();
  const next = safeNext(useSearchParams().get("next"));
  const login = useLogin();
  const errorMessage = useErrorMessage();
  const [formError, setFormError] = useState<string | null>(null);

  const form = useForm<LoginInput>({
    resolver: zodResolver(loginSchema(tv)),
    defaultValues: { email: "", password: "", remember: false },
  });
  const { errors } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      await login.mutateAsync(values);
      router.replace(next);
    } catch (error) {
      // Wrong credentials and lockouts are about the attempt, not one field: show them above the form.
      setFormError(errorMessage(error));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {formError ? <Alert tone="error">{formError}</Alert> : null}
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
        autoComplete="current-password"
        showLabel={t("showPassword")}
        hideLabel={t("hidePassword")}
        error={errors.password?.message}
        aside={
          <Link href="/forgot-password" className="rounded-chip text-sm text-muted underline-offset-4 hover:text-ink hover:underline">
            {t("forgotLink")}
          </Link>
        }
        {...form.register("password")}
      />
      <Checkbox label={t("remember")} {...form.register("remember")} />
      <Button type="submit" loading={form.formState.isSubmitting} className="w-full">
        {t("loginSubmit")}
      </Button>
    </form>
  );
}
