import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { LabEditor } from "@/features/lab-admin/components/lab-editor";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("labAdmin.editor");
  return { title: t("pageTitle") };
}

export default async function AdminLabEditorPage({ params }: PageProps<"/admin/labs/[id]">) {
  const id = Number((await params).id);
  if (!Number.isInteger(id) || id <= 0) notFound();
  return <LabEditor id={id} />;
}
