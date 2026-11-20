import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { AttemptRunner } from "@/features/assessment/components/attempt-runner";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("assessment");
  return { title: t("title") };
}

export default async function AttemptPage({ params }: PageProps<"/assessment/attempts/[id]">) {
  const id = Number((await params).id);
  if (!Number.isSafeInteger(id) || id < 1) notFound();
  return <AttemptRunner key={id} id={id} />;
}
