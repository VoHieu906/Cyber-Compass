import Link from "next/link";
import { getTranslations } from "next-intl/server";
import { HealthPanel } from "@/features/system/components/health-panel";

/**
 * System status for developers and operators (not linked from the UI).
 * Shows whether the browser can reach the API and the API its database.
 */
export default async function StatusPage() {
  const t = await getTranslations("home");
  return (
    <div className="container-page grid gap-12 py-[var(--space-3xl)] lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)] lg:items-start lg:gap-[var(--gap-columns)]">
      <div className="flex max-w-[34rem] flex-col gap-6">
        <h1 className="text-[length:var(--text-display-s)]">{t("title")}</h1>
        <p className="text-[length:var(--text-lead)] text-ink-2">{t("lede")}</p>
        <Link
          href="/design-system"
          className="w-fit rounded-chip text-ink underline decoration-rule-2 underline-offset-4 transition-[text-decoration-color] duration-[var(--dur-micro)] hover:decoration-accent"
        >
          {t("designSystemLink")}
        </Link>
      </div>
      <HealthPanel title={t("statusTitle")} />
    </div>
  );
}
