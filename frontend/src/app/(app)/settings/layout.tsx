import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { SettingsNav } from "@/features/profile/components/settings-nav";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("settings");
  return { title: t("title") };
}

export default async function SettingsLayout({ children }: LayoutProps<"/settings">) {
  const t = await getTranslations("settings");
  return (
    <div className="container-page flex flex-col gap-8 py-[var(--space-2xl)]">
      <h1 className="text-[length:var(--text-2xl)]">{t("title")}</h1>
      <div className="grid gap-8 lg:grid-cols-[minmax(0,14rem)_minmax(0,1fr)] lg:gap-[var(--gap-columns)]">
        <SettingsNav />
        <div className="min-w-0 max-w-[44rem]">{children}</div>
      </div>
    </div>
  );
}
