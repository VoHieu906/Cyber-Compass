import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { RoadmapView } from "@/features/roadmap/components/roadmap-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("roadmap");
  return { title: t("title") };
}

/** My personal roadmap, as an outline (ARCHITECTURE D25). The graph view arrives in Phase 5. */
export default function RoadmapPage() {
  return <RoadmapView />;
}
