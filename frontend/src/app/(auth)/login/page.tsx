import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AuthPanel, TextLink } from "@/features/auth/components/auth-panel";
import { LoginForm } from "@/features/auth/components/login-form";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("auth");
  return { title: t("loginTitle") };
}

export default async function LoginPage() {
  const t = await getTranslations("auth");
  return (
    <AuthPanel
      title={t("loginTitle")}
      lede={t("loginLede")}
      footer={
        <p>
          {t("noAccount")} <TextLink href="/register">{t("registerLink")}</TextLink>
        </p>
      }
    >
      <LoginForm />
    </AuthPanel>
  );
}
