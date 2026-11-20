import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";
import { CourseStructureView } from "@/features/content-editor/components/course-structure-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("content.courses");
  return { title: t("title") };
}

export default function AdminCoursesPage() {
  return (
    <Suspense>
      <CourseStructureView />
    </Suspense>
  );
}
