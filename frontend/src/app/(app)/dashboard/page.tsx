import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { DashboardView } from "@/features/progress/components/dashboard-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("dashboard");
  return { title: t("pageTitle") };
}

/** The learner's home after login (D35). */
export default function DashboardPage() {
  return <DashboardView />;
}
