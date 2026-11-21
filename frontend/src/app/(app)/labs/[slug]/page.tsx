import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { LabView } from "@/features/labs/components/lab-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("labs");
  return { title: t("title") };
}

/** One lab: briefing, checkpoints and the engine's console. */
export default async function LabPage({ params }: PageProps<"/labs/[slug]">) {
  const { slug } = await params;
  return <LabView key={slug} slug={slug} />;
}
