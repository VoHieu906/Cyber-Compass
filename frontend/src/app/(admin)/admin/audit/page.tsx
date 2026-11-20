import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";
import { AuditView } from "@/features/admin/components/audit-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("admin.audit");
  return { title: t("title") };
}

export default async function AdminAuditPage() {
  const t = await getTranslations("admin.audit");
  return (
    <div className="flex flex-col gap-8">
      <h1 className="text-[length:var(--text-2xl)]">{t("title")}</h1>
      <Suspense>
        <AuditView />
      </Suspense>
    </div>
  );
}
