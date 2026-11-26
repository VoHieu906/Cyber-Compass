import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { LabsAdminView } from "@/features/lab-admin/components/labs-admin-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("labAdmin");
  return { title: t("title") };
}

export default function AdminLabsPage() {
  return <LabsAdminView />;
}
