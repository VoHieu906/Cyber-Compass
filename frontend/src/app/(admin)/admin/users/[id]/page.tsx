import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { UserDetailView } from "@/features/admin/components/user-detail-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("admin.user");
  return { title: t("title") };
}

export default async function AdminUserPage({ params }: PageProps<"/admin/users/[id]">) {
  const id = Number((await params).id);
  if (!Number.isInteger(id) || id <= 0) notFound();
  return <UserDetailView id={id} />;
}
