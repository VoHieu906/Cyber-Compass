import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { ExerciseView } from "@/features/exercise/components/exercise-view";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("exercise");
  return { title: t("crumb") };
}

/** A lesson's exercise: theory beside the questions, scored by the API (ARCHITECTURE D31). */
export default async function ExercisePage({ params }: PageProps<"/learn/[slug]/exercise">) {
  const { slug } = await params;
  return <ExerciseView key={slug} slug={slug} />;
}
