import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { RoadmapView } from "@/features/roadmap/components/roadmap-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("roadmap");
  return { title: t("title") };
}

/** My personal roadmap: a graph on wide screens (D27), an outline on phones (D25). */
export default function RoadmapPage() {
  return <RoadmapView />;
}
