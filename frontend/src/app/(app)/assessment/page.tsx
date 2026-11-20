import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AssessmentOverview } from "@/features/assessment/components/assessment-overview";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("assessment");
  return { title: t("title"), description: t("metaDescription") };
}

/** The assessment intro: what it covers, how it is scored, start or resume, past attempts. */
export default function AssessmentPage() {
  return <AssessmentOverview />;
}
