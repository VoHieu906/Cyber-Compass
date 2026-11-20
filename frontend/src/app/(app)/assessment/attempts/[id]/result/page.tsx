import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { ResultView } from "@/features/assessment/components/result-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("assessment.result");
  return { title: t("title") };
}

export default async function ResultPage({ params }: PageProps<"/assessment/attempts/[id]/result">) {
  const id = Number((await params).id);
  if (!Number.isSafeInteger(id) || id < 1) notFound();
  return <ResultView key={id} id={id} />;
}
