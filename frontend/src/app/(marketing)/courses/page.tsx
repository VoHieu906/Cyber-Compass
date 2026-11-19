import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";
import { CatalogView } from "@/features/catalog/components/catalog-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("catalog");
  return { title: t("title"), description: t("lede") };
}

export default async function CoursesPage() {
  const t = await getTranslations("catalog");
  return (
    <div className="container-page flex flex-col gap-8 py-[var(--space-2xl)]">
      <header className="flex max-w-[40rem] flex-col gap-3">
        <h1 className="text-[length:var(--text-display-s)]">{t("title")}</h1>
        <p className="text-[length:var(--text-lead)] text-ink-2">{t("lede")}</p>
      </header>
      <Suspense>
        <CatalogView />
      </Suspense>
    </div>
  );
}
