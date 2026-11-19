import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AuthPanel, TextLink } from "@/features/auth/components/auth-panel";
import { ForgotPasswordForm } from "@/features/auth/components/forgot-password-form";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("auth");
  return { title: t("forgotTitle") };
}

export default async function ForgotPasswordPage() {
  const t = await getTranslations("auth");
  return (
    <AuthPanel
      title={t("forgotTitle")}
      lede={t("forgotLede")}
      footer={<TextLink href="/login">{t("backToLogin")}</TextLink>}
    >
      <ForgotPasswordForm />
    </AuthPanel>
  );
}
