import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { LessonEditor } from "@/features/content-editor/components/lesson-editor";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("content.editor");
  return { title: t("pageTitle") };
}

export default async function AdminLessonEditorPage({ params }: PageProps<"/admin/lessons/[id]">) {
  const id = Number((await params).id);
  if (!Number.isInteger(id) || id <= 0) notFound();
  return <LessonEditor id={id} />;
}
