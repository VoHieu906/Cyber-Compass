import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { KnowledgeMapView } from "@/features/roadmap/components/knowledge-map-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("roadmap.map");
  return { title: t("title") };
}

/** The knowledge map: every topic by domain and level, with my status (ARCHITECTURE D27). */
export default function KnowledgeMapPage() {
  return <KnowledgeMapView />;
}
