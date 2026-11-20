import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";
import { UsersView } from "@/features/admin/components/users-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("admin.users");
  return { title: t("title") };
}

export default async function AdminUsersPage() {
  const t = await getTranslations("admin.users");
  return (
    <div className="flex flex-col gap-8">
      <h1 className="text-[length:var(--text-2xl)]">{t("title")}</h1>
      <Suspense>
        <UsersView />
      </Suspense>
    </div>
  );
}
