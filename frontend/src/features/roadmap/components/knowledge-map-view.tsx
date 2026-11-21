"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { useMemo, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { BandChip, ProgressTrack } from "@/features/assessment/components/parts";
import type { GridMetrics } from "@/lib/graph/grid";
import { useMediaQuery } from "@/lib/use-media-query";
import { cn } from "@/lib/utils";
import { useKnowledgeMap } from "../hooks";
import type { KnowledgeMap, KnowledgeMapDomain } from "../types";
import { LessonDrawer, type DrawerLesson } from "./lesson-drawer";
import { linkClass, OutlineTopic, WIDE } from "./outline";
import { RoadmapTabs } from "./roadmap-tabs";
import { TopicGraph, useExpandedTopics, type GraphTopic } from "./topic-graph";

const METRICS: GridMetrics = {
  left: 236,
  top: 32,
  colWidth: 248,
  colGap: 72,
  rowGap: 33,
  itemGap: 16,
  nodeHeight: 128,
  childHeight: 36,
  childGap: 8,
  minRowHeight: 128,
};

/** Every topic I can study, by domain and level, with my status: the whole field, not just my plan. */
export function KnowledgeMapView() {
  const t = useTranslations("roadmap.map");
  const map = useKnowledgeMap();
  const wide = useMediaQuery(WIDE);
  const expanded = useExpandedTopics();

  return (
    <div className="container-page flex flex-col gap-8 py-[var(--space-xl)]">
      <header className="flex flex-col gap-4">
        <h1 className="text-[length:var(--text-display-s)]">{t("title")}</h1>
        <RoadmapTabs />
        <p className="max-w-[68ch] text-[length:var(--text-lead)] text-ink-2">{t("intro")}</p>
      </header>
      {map.isError ? (
        <Alert tone="error">{t("loadError")}</Alert>
      ) : map.isPending ? (
        <div role="status" className="min-h-[50vh]" />
      ) : (
        <>
          <p className="max-w-[68ch] text-sm">{t("legend")}</p>
          {wide ? <MapGraph map={map.data} expanded={expanded} /> : <MapOutline map={map.data} />}
        </>
      )}
    </div>
  );
}

function useTopicText(map: KnowledgeMap) {
  const t = useTranslations("roadmap");
  return useMemo(() => {
    const titles = new Map(map.topics.map((tp) => [tp.slug, tp.title]));
    const domains = new Map(map.domains.map((d) => [d.slug, d.name]));
    return {
      needs: (requires: string[]) => (requires.length ? t("needs", { topics: requires.map((s) => titles.get(s) ?? s).join(", ") }) : ""),
      domain: (slug: string) => domains.get(slug) ?? slug,
    };
  }, [map, t]);
}

function MapGraph({ map, expanded }: { map: KnowledgeMap; expanded: ReturnType<typeof useExpandedTopics> }) {
  const t = useTranslations("roadmap");
  const text = useTopicText(map);
  const [drawer, setDrawer] = useState<DrawerLesson | null>(null);

  const levels = useMemo(() => [...new Set(map.topics.map((tp) => tp.level))].sort((a, b) => a - b), [map.topics]);
  const rows = useMemo(() => new Map(map.domains.map((d, i) => [d.slug, i])), [map.domains]);
  const topics = useMemo<GraphTopic[]>(
    () =>
      map.topics.map((tp) => ({
        slug: tp.slug,
        title: tp.title,
        status: tp.status,
        percent: tp.percent,
        row: rows.get(tp.domain) ?? 0,
        col: levels.indexOf(tp.level),
        meta: tp.in_path ? t("map.onRoadmap") : undefined,
        description: [t(`status.${tp.status}`), text.domain(tp.domain), t(`map.level.${tp.level}`), tp.in_path ? t("map.onRoadmap") : "", text.needs(tp.requires)]
          .filter(Boolean)
          .join(". "),
        requires: tp.requires,
        lessons: tp.lessons,
      })),
    [map.topics, rows, levels, t, text],
  );

  return (
    <>
      <TopicGraph
        label={t("map.label")}
        topics={topics}
        rowCount={map.domains.length}
        colCount={levels.length}
        metrics={METRICS}
        columnHeader={(col) => t(`map.level.${levels[col]}`)}
        rowHeader={(row) => <DomainSummary domain={map.domains[row]} />}
        expanded={expanded.expanded}
        onToggle={expanded.toggle}
        onOpenLesson={(topic, lesson) => setDrawer({ topic: topic.title, lesson })}
      />
      <LessonDrawer value={drawer} onClose={() => setDrawer(null)} />
    </>
  );
}

/** A domain's latest assessment band and score and its lesson completion. */
function DomainSummary({ domain, heading = false }: { domain: KnowledgeMapDomain; heading?: boolean }) {
  const t = useTranslations("roadmap.map");
  const Name = heading ? "h2" : "p";
  return (
    <div className="flex flex-col gap-2">
      <Name className={cn("font-medium text-ink", heading && "text-[length:var(--text-title)]")}>{domain.name}</Name>
      <p className="flex flex-wrap items-center gap-2 text-sm">
        {domain.band !== null && domain.score !== null ? (
          <>
            <BandChip band={domain.band} />
            <span className="measured text-ink">{t("score", { score: domain.score })}</span>
          </>
        ) : (
          <Link href="/assessment" className={linkClass}>
            {t("notAssessed")}
          </Link>
        )}
      </p>
      {domain.completion.total ? (
        <div className="flex flex-col gap-1.5 text-sm">
          <span>{t("completion", { completed: domain.completion.completed, total: domain.completion.total })}</span>
          <ProgressTrack value={domain.completion.percent} className="max-w-[12rem]" />
        </div>
      ) : (
        <p className="text-sm">{t("noLessons")}</p>
      )}
    </div>
  );
}

/** Phones: the same map as a list, domain by domain. */
function MapOutline({ map }: { map: KnowledgeMap }) {
  const t = useTranslations("roadmap");
  const text = useTopicText(map);
  return (
    <ol className="flex flex-col gap-10">
      {map.domains.map((domain) => {
        const topics = map.topics.filter((tp) => tp.domain === domain.slug);
        return (
          <li key={domain.slug} className="flex flex-col gap-4 border-t border-rule pt-6">
            <DomainSummary domain={domain} heading />
            {topics.length ? (
              <ol className="flex flex-col gap-3">
                {topics.map((tp) => (
                  <OutlineTopic
                    key={tp.slug}
                    title={tp.title}
                    status={tp.status}
                    percent={tp.percent}
                    meta={tp.in_path ? t("map.onRoadmap") : undefined}
                    lessons={tp.lessons}
                    details={
                      <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <span>{t(`map.level.${tp.level}`)}</span>
                        {tp.status === "locked" && tp.requires.length ? <span>{text.needs(tp.requires)}</span> : null}
                      </p>
                    }
                  />
                ))}
              </ol>
            ) : null}
          </li>
        );
      })}
    </ol>
  );
}
