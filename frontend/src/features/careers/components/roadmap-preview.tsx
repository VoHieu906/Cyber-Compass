import { getTranslations } from "next-intl/server";
import { DomainIcon } from "@/features/catalog/domain-icons";
import type { CareerDetail } from "../types";

/**
 * The career's roadmap template, read-only: stages as columns, topics in order, each
 * naming what it builds on. The interactive, personal graph arrives with Phase 5;
 * this preview renders the same data.
 */
export async function RoadmapPreview({ stages }: { stages: CareerDetail["roadmap"]["stages"] }) {
  const t = await getTranslations();
  const titles = new Map(stages.flatMap((s) => s.topics.map((topic) => [topic.slug, topic.title] as const)));

  return (
    <ol className="grid gap-px overflow-hidden rounded-card border border-rule bg-rule md:grid-cols-3">
      {stages.map((stage) => (
        <li key={stage.stage} className="flex flex-col gap-3 bg-paper p-4">
          <h3 className="measured text-sm font-normal text-ink">{t("careers.stage", { number: stage.stage })}</h3>
          <ol className="flex flex-col gap-2">
            {stage.topics.map((topic) => (
              <li key={topic.slug} className="flex flex-col gap-2 rounded-control border border-rule bg-paper-2 p-3.5">
                <div className="flex items-start gap-2.5">
                  <DomainIcon slug={topic.domain.slug} className="mt-0.5 size-4 shrink-0 text-muted" />
                  <div className="flex min-w-0 flex-col gap-0.5">
                    <p className="font-medium text-ink">{topic.title}</p>
                    <p className="text-sm">{topic.summary}</p>
                  </div>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 pl-6 text-sm">
                  <span className="measured text-ink">{t("units.lessons", { count: topic.lesson_count })}</span>
                  {topic.requires.length ? (
                    <span>{t("careers.requires", { topics: topic.requires.map((s) => titles.get(s) ?? s).join(", ") })}</span>
                  ) : null}
                </div>
              </li>
            ))}
          </ol>
        </li>
      ))}
    </ol>
  );
}
