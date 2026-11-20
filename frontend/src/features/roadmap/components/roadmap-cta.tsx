"use client";

import { ArrowRight } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import { useCurrentUser } from "@/features/auth/hooks";
import { useLearningPath } from "../hooks";
import { BuildRoadmapButton } from "./build-roadmap-button";

/**
 * The next move after an assessment: choose a career, build the roadmap, or update it when it
 * was built before this result. Never rebuilds on its own (ARCHITECTURE D26).
 */
export function RoadmapCta() {
  const t = useTranslations("roadmap");
  const { data: user } = useCurrentUser();
  const hasGoal = Boolean(user?.learning_goal?.career);
  const path = useLearningPath(hasGoal);

  if (!hasGoal) {
    return (
      <Link href="/careers" className={buttonVariants()}>
        {t("chooseCareer")}
        <ArrowRight aria-hidden />
      </Link>
    );
  }
  if (path.isPending) return <div aria-hidden className="h-10 w-48" />;
  if (!path.data) return <BuildRoadmapButton />;
  if (path.data.outdated.length) return <BuildRoadmapButton update />;
  return (
    <Link href="/roadmap" className={buttonVariants()}>
      {t("open")}
      <ArrowRight aria-hidden />
    </Link>
  );
}
