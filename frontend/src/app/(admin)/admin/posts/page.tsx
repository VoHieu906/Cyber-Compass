import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { PostsView } from "@/features/content-editor/components/posts-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("blog.admin");
  return { title: t("title") };
}

export default function AdminPostsPage() {
  return <PostsView />;
}
