import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Alert } from "@/components/ui/alert";
import { AuthPanel, TextLink } from "@/features/auth/components/auth-panel";
import { ResetPasswordForm } from "@/features/auth/components/reset-password-form";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("auth");
  return { title: t("resetTitle") };
}

/** Opened from the emailed link: /reset-password?token=…&email=… */
export default async function ResetPasswordPage({ searchParams }: PageProps<"/reset-password">) {
  const t = await getTranslations("auth");
  const params = await searchParams;
  const token = typeof params.token === "string" ? params.token : "";
  const email = typeof params.email === "string" ? params.email : "";

  return (
    <AuthPanel
      title={t("resetTitle")}
      lede={email ? t("resetLede", { email }) : undefined}
      footer={<TextLink href="/login">{t("backToLogin")}</TextLink>}
    >
      {token && email ? (
        <ResetPasswordForm token={token} email={email} />
      ) : (
        <Alert tone="error">
          <p>{t("resetInvalidLink")}</p>
          <a href="/forgot-password" className="mt-1 inline-block text-ink underline underline-offset-4">
            {t("requestNewLink")}
          </a>
        </Alert>
      )}
    </AuthPanel>
  );
}
