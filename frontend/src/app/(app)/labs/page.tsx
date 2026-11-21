import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { LabsList } from "@/features/labs/components/labs-list";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("labs");
  return { title: t("title") };
}

/** Practical labs: simulations run on the server (ARCHITECTURE §7, D28). */
export default function LabsPage() {
  return <LabsList />;
}
