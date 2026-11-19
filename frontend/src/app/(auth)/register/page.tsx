import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AuthPanel, TextLink } from "@/features/auth/components/auth-panel";
import { RegisterForm } from "@/features/auth/components/register-form";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("auth");
  return { title: t("registerTitle") };
}

export default async function RegisterPage() {
  const t = await getTranslations("auth");
  return (
    <AuthPanel
      title={t("registerTitle")}
      lede={t("registerLede")}
      footer={
        <p>
          {t("haveAccount")} <TextLink href="/login">{t("loginLink")}</TextLink>
        </p>
      }
    >
      <RegisterForm />
    </AuthPanel>
  );
}
