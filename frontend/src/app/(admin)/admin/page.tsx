import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { OverviewView } from "@/features/admin/components/overview-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("admin.overview");
  return { title: t("title") };
}

export default async function AdminOverviewPage() {
  const t = await getTranslations("admin.overview");
  return (
    <div className="flex flex-col gap-8">
      <h1 className="text-[length:var(--text-2xl)]">{t("title")}</h1>
      <OverviewView />
    </div>
  );
}
