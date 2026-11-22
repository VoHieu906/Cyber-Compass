import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { PostEditor } from "@/features/content-editor/components/post-editor";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("blog.editor");
  return { title: t("pageTitle") };
}

export default async function AdminPostEditorPage({ params }: PageProps<"/admin/posts/[id]">) {
  const id = Number((await params).id);
  if (!Number.isInteger(id) || id <= 0) notFound();
  return <PostEditor id={id} />;
}
