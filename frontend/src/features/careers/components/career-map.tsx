"use client";

import { ArrowRight } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useState } from "react";
import { useTabs } from "@/features/learning/diagrams/use-tabs";
import { cn } from "@/lib/utils";
import type { CareerDetail } from "../types";

// Graph geometry in SVG units; nodes are placed over it as percentages.
const COL_W = 200;
const COL_GAP = 56;
const NODE_H = 92;
const ROW_GAP = 20;
const PAD = 12;

type Career = Pick<CareerDetail, "slug" | "title" | "roadmap">;
type Topic = Career["roadmap"]["stages"][number]["topics"][number];

/**
 * A career's roadmap template as a graph: stages are columns, topics are nodes,
 * prerequisites are edges, and pointing at a topic lights what it builds on.
 * From 60rem the graph is one image (role=img) whose accessible name lists every stage,
 * topic and prerequisite; below that the same data is an ordered list of stages.
 */
export function CareerMap({ careers }: { careers: Career[] }) {
  const t = useTranslations();
  const tabs = useTabs(careers.length);
  const career = careers[tabs.selected];
  const [focused, setFocused] = useState<string | null>(null);

  const stages = career.roadmap.stages;
  const topics = stages.flatMap((s) => s.topics);
  const titles = new Map(topics.map((tp) => [tp.slug, tp.title] as const));
  const rows = Math.max(...stages.map((s) => s.topics.length));
  const width = PAD * 2 + stages.length * COL_W + (stages.length - 1) * COL_GAP;
  const height = PAD * 2 + rows * NODE_H + (rows - 1) * ROW_GAP;

  const pos = new Map<string, { x: number; y: number }>();
  stages.forEach((stage, col) => {
    const columnHeight = stage.topics.length * NODE_H + (stage.topics.length - 1) * ROW_GAP;
    const top = (height - columnHeight) / 2;
    stage.topics.forEach((topic, row) => pos.set(topic.slug, { x: PAD + col * (COL_W + COL_GAP), y: top + row * (NODE_H + ROW_GAP) }));
  });

  const lit = new Set(focused ? (topics.find((tp) => tp.slug === focused)?.requires ?? []) : []);
  const edges = topics.flatMap((topic) => topic.requires.filter((r) => pos.has(r)).map((from) => ({ from, to: topic.slug })));
  const describe = (topic: Topic) =>
    [t("units.lessons", { count: topic.lesson_count }), topic.requires.length ? t("careers.requires", { topics: topic.requires.map((r) => titles.get(r) ?? r).join(", ") }) : ""]
      .filter(Boolean)
      .join(". ");
  // The graph is one image; its accessible name carries every stage, topic and prerequisite.
  const summary = [
    career.title,
    ...stages.map((stage) => `${t("careers.stage", { number: stage.stage })}: ${stage.topics.map((tp) => `${tp.title} (${describe(tp)})`).join("; ")}`),
  ].join(". ");
  const nodeClass = (slug: string) =>
    cn(
      "flex flex-col items-start justify-center gap-0.5 rounded-control border px-3 text-left",
      "transition-[background-color,border-color] duration-[var(--dur-micro)] ease-out",
      focused === slug ? "border-accent bg-accent-soft" : lit.has(slug) ? "border-accent bg-paper-2" : "border-rule-2 bg-paper-2 hover:border-muted",
    );

  return (
    <div className="flex flex-col gap-3">
      <div {...tabs.listProps} aria-label={t("careers.title")} className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 min-[60rem]:flex-wrap min-[60rem]:overflow-visible">
        {careers.map((c, i) => (
          <button
            key={c.slug}
            type="button"
            {...tabs.tabProps(i)}
            className={cn(
              "shrink-0 rounded-control border px-3 py-1.5 text-sm font-medium whitespace-nowrap",
              "transition-[background-color,border-color,color] duration-[var(--dur-micro)] ease-out",
              i === tabs.selected ? "border-accent bg-accent-soft text-ink" : "border-rule text-muted hover:border-rule-2 hover:text-ink",
            )}
          >
            {c.title}
          </button>
        ))}
      </div>

      <div {...tabs.panelProps} className="panel overflow-hidden">
        {/* Wide: the graph. */}
        <div role="img" aria-label={summary} className="bg-paper-3 p-4 max-[59.99rem]:hidden">
          <div className="relative w-full" style={{ aspectRatio: `${width} / ${height}` }}>
            <svg viewBox={`0 0 ${width} ${height}`} className="absolute inset-0 size-full" aria-hidden>
              {stages.map((stage, col) => (
                <text
                  key={stage.stage}
                  x={PAD + col * (COL_W + COL_GAP)}
                  y={8}
                  fontSize="11"
                  fill="var(--color-muted)"
                  fontFamily="var(--font-measured)"
                >
                  {t("careers.stage", { number: stage.stage })}
                </text>
              ))}
              {edges.map(({ from, to }) => {
                const a = pos.get(from)!;
                const b = pos.get(to)!;
                const x1 = a.x + COL_W;
                const y1 = a.y + NODE_H / 2;
                const x2 = b.x;
                const y2 = b.y + NODE_H / 2;
                const mid = (x1 + x2) / 2;
                const active = focused === to;
                return (
                  <path
                    key={`${from}-${to}`}
                    d={`M ${x1} ${y1} C ${mid} ${y1}, ${mid} ${y2}, ${x2} ${y2}`}
                    fill="none"
                    stroke={active ? "var(--color-accent)" : "var(--color-rule-2)"}
                    strokeWidth={active ? 2.25 : 1.25}
                  />
                );
              })}
            </svg>
            <ol>
              {topics.map((topic) => {
                const p = pos.get(topic.slug)!;
                return (
                  <li
                    key={topic.slug}
                    className="absolute"
                    style={{
                      left: `${(p.x / width) * 100}%`,
                      top: `${(p.y / height) * 100}%`,
                      width: `${(COL_W / width) * 100}%`,
                      height: `${(NODE_H / height) * 100}%`,
                    }}
                  >
                    <div
                      className={cn("size-full", nodeClass(topic.slug))}
                      onMouseEnter={() => setFocused(topic.slug)}
                      onMouseLeave={() => setFocused(null)}
                    >
                      <span className="line-clamp-3 text-base leading-snug font-medium text-ink">{topic.title}</span>
                    </div>
                  </li>
                );
              })}
            </ol>
          </div>
        </div>

        {/* Narrow screens: the same data as a list of stages. */}
        <ol aria-label={career.title} className="flex flex-col gap-4 bg-paper-3 p-4 min-[60rem]:hidden">
          {stages.map((stage) => (
            <li key={stage.stage} className="flex flex-col gap-2">
              <p className="measured text-sm text-ink">{t("careers.stage", { number: stage.stage })}</p>
              <ol className="flex flex-col gap-2">
                {stage.topics.map((topic) => (
                  <li key={topic.slug} className={cn("py-2.5", nodeClass(topic.slug))}>
                    <span className="font-medium text-ink">{topic.title}</span>
                    <span className="text-sm">{describe(topic)}</span>
                  </li>
                ))}
              </ol>
            </li>
          ))}
        </ol>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-rule bg-paper-2 px-4 py-3">
          <p className="text-sm">{t("landing.mapLegend")}</p>
          <Link
            href={`/careers/${career.slug}`}
            className="inline-flex items-center gap-1.5 text-sm font-medium text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent"
          >
            {t("careers.viewCareer", { title: career.title })}
            <ArrowRight aria-hidden className="size-3.5" />
          </Link>
        </div>
      </div>
    </div>
  );
}
