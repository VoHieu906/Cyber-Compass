"use client";

import { useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import type { GridMetrics } from "@/lib/graph/grid";
import type { LearningPath } from "../types";
import { LessonDrawer, type DrawerLesson } from "./lesson-drawer";
import { TopicGraph, type GraphTopic, type useExpandedTopics } from "./topic-graph";

const METRICS: GridMetrics = {
  left: 0,
  top: 32,
  colWidth: 248,
  colGap: 72,
  rowGap: 0,
  itemGap: 20,
  nodeHeight: 100,
  childHeight: 36,
  childGap: 8,
};

/**
 * My roadmap as a graph (wide screens, ARCHITECTURE D27): stages are columns, topics are
 * nodes in path order, prerequisites are edges, and lessons open as child rows under their
 * topic. The topic holding the next lesson starts open and is ringed.
 */
export function RoadmapGraph({
  path,
  nextTopic,
  expanded,
}: {
  path: LearningPath;
  nextTopic: string | undefined;
  expanded: ReturnType<typeof useExpandedTopics>;
}) {
  const t = useTranslations("roadmap");
  const [drawer, setDrawer] = useState<DrawerLesson | null>(null);

  const stages = useMemo(() => [...new Set(path.nodes.map((n) => n.stage))].sort((a, b) => a - b), [path.nodes]);
  const topics = useMemo<GraphTopic[]>(() => {
    const titles = new Map(path.nodes.map((n) => [n.topic.slug, n.topic.title]));
    return [...path.nodes]
      .sort((a, b) => a.stage - b.stage || a.position - b.position)
      .map((node) => ({
        slug: node.topic.slug,
        title: node.topic.title,
        status: node.status,
        percent: node.percent,
        row: 0,
        col: stages.indexOf(node.stage),
        review: node.recommendation === "review",
        description: [
          t(`status.${node.status}`),
          t(`reason.${node.reason.key}`, node.reason.params),
          node.requires.length ? t("needs", { topics: node.requires.map((s) => titles.get(s) ?? s).join(", ") }) : "",
        ]
          .filter(Boolean)
          .join(" "),
        requires: node.requires,
        lessons: node.lessons,
      }));
  }, [path.nodes, stages, t]);

  return (
    <section aria-labelledby="graph-heading" className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 id="graph-heading" className="text-[length:var(--text-title)]">
          {t("graph.title")}
        </h2>
        <div className="flex gap-2">
          <Button variant="secondary" size="sm" onClick={() => expanded.set(topics.map((tp) => tp.slug))}>
            {t("graph.expandAll")}
          </Button>
          <Button variant="secondary" size="sm" onClick={() => expanded.set([])}>
            {t("graph.collapseAll")}
          </Button>
        </div>
      </div>
      <TopicGraph
        label={t("graph.label", { career: path.career.title })}
        topics={topics}
        rowCount={1}
        colCount={stages.length}
        metrics={METRICS}
        columnHeader={(col) => t("stage", { stage: stages[col] })}
        expanded={expanded.expanded}
        onToggle={expanded.toggle}
        onOpenLesson={(topic, lesson) => setDrawer({ topic: topic.title, lesson })}
        highlight={nextTopic}
      />
      <LessonDrawer value={drawer} onClose={() => setDrawer(null)} />
    </section>
  );
}
