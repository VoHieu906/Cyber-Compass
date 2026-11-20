import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";
import { LessonsView } from "@/features/content-editor/components/lessons-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("content.lessons");
  return { title: t("title") };
}

export default function AdminLessonsPage() {
  return (
    <Suspense>
      <LessonsView />
    </Suspense>
  );
}
