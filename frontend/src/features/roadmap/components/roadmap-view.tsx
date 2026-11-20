"use client";

import { ArrowRight, Check, ChevronDown, Circle, CircleDot, RefreshCw } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import { useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { buttonVariants } from "@/components/ui/button";
import { ProgressTrack } from "@/features/assessment/components/parts";
import { useActiveAssessment } from "@/features/assessment/hooks";
import { useCurrentUser } from "@/features/auth/hooks";
import { Duration } from "@/features/catalog/components/labels";
import { cn } from "@/lib/utils";
import { useLearningPath, usePathVersions } from "../hooks";
import type { LearningPath, PathLesson, PathNode } from "../types";
import { BuildRoadmapButton } from "./build-roadmap-button";
import { RoadmapNodeCard } from "./node-status";

const linkClass = "text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent";

export function RoadmapView() {
  const t = useTranslations("roadmap");
  const path = useLearningPath();

  if (path.isError) {
    return (
      <div className="container-page max-w-[44rem] py-[var(--space-2xl)]">
        <Alert tone="error">{t("loadError")}</Alert>
      </div>
    );
  }
  if (path.isPending) return <div role="status" className="min-h-[60vh]" />;
  return path.data ? <Roadmap path={path.data} /> : <EmptyRoadmap />;
}

/** No roadmap yet: what is needed to build one, and the action that builds it. */
function EmptyRoadmap() {
  const t = useTranslations("roadmap");
  const format = useFormatter();
  const { data: user } = useCurrentUser();
  const assessment = useActiveAssessment();
  const career = user?.learning_goal?.career ?? null;
  const latest = assessment.data?.latest_result ?? null;

  return (
    <div className="container-page flex max-w-[44rem] flex-col gap-6 py-[var(--space-2xl)]">
      <h1 className="text-[length:var(--text-display-s)]">{t("title")}</h1>
      {career ? (
        <>
          <p className="text-[length:var(--text-lead)] text-ink-2">{t("empty.goal", { career: career.title })}</p>
          <p>
            {latest
              ? t("empty.withAssessment", { score: latest.score, date: format.dateTime(new Date(latest.submitted_at), { dateStyle: "medium" }) })
              : t("empty.withoutAssessment")}
          </p>
          <div className="flex flex-wrap items-start gap-3">
            <BuildRoadmapButton />
            {latest ? null : (
              <Link href="/assessment" className={buttonVariants({ variant: "secondary" })}>
                {t("takeAssessment")}
              </Link>
            )}
          </div>
        </>
      ) : (
        <>
          <p className="text-[length:var(--text-lead)] text-ink-2">{t("empty.noGoal")}</p>
          <div className="flex flex-wrap gap-3">
            <Link href="/careers" className={buttonVariants()}>
              {t("chooseCareer")}
              <ArrowRight aria-hidden />
            </Link>
            <Link href="/settings/goals" className={buttonVariants({ variant: "secondary" })}>
              {t("editGoal")}
            </Link>
          </div>
        </>
      )}
    </div>
  );
}

function Roadmap({ path }: { path: LearningPath }) {
  const t = useTranslations("roadmap");
  const format = useFormatter();
  const stages = [...new Set(path.nodes.map((n) => n.stage))].map((stage) => ({ stage, nodes: path.nodes.filter((n) => n.stage === stage) }));
  const titles = new Map(path.nodes.map((n) => [n.topic.slug, n.topic.title]));
  const nextTopic = path.nodes.find((n) => n.lessons.some((l) => l.slug === path.next_lesson?.slug))?.topic.slug;

  return (
    <div className="container-page flex flex-col gap-10 py-[var(--space-xl)]">
      <header className="flex flex-col gap-4">
        <h1 className="text-[length:var(--text-display-s)]">{t("title")}</h1>
        <p className="flex flex-wrap items-baseline gap-x-5 gap-y-1 text-sm">
          <Link href={`/careers/${path.career.slug}`} className={cn(linkClass, "text-[length:var(--text-lg)]")}>
            {path.career.title}
          </Link>
          <span className="measured">{t("version", { version: path.version })}</span>
          <span>{t("generated", { date: format.dateTime(new Date(path.generated_at), { dateStyle: "medium" }) })}</span>
          <span>{path.assessment ? t("basedOn", { score: path.assessment.score }) : t("noAssessment")}</span>
        </p>
      </header>

      {path.outdated.length ? (
        <section aria-label={t("outdatedTitle")} className="flex flex-col gap-3 rounded-card border border-attn bg-attn-soft p-5">
          <p className="flex items-center gap-2 font-medium text-ink">
            <RefreshCw aria-hidden className="size-4" />
            {t("outdatedTitle")}
          </p>
          <ul className="flex flex-col gap-1 pl-6 text-sm text-ink [list-style:disc]">
            {path.outdated.map((reason) => (
              <li key={reason}>{t(`outdated.${reason}`)}</li>
            ))}
          </ul>
          <BuildRoadmapButton update stay size="sm" className="self-start" />
        </section>
      ) : null}

      <section aria-label={t("summary")} className="grid gap-px overflow-hidden rounded-card border border-rule bg-rule md:grid-cols-3">
        <div className="flex flex-col gap-3 bg-paper-2 p-5">
          <p className="text-sm text-ink">{t("completion")}</p>
          <p className="measured text-[length:var(--text-2xl)] leading-none text-ink">{path.completion_percent}%</p>
          <ProgressTrack value={path.completion_percent} />
        </div>
        <div className="flex flex-col gap-2 bg-paper-2 p-5">
          <p className="text-sm text-ink">{t("remaining")}</p>
          <Duration minutes={path.estimate.remaining_minutes} className="text-[length:var(--text-2xl)] leading-none text-ink" />
          <p className="text-sm">
            {path.estimate.weeks !== null && path.estimate.weekly_minutes !== null ? (
              t("weeks", { weeks: path.estimate.weeks, hours: Math.round((path.estimate.weekly_minutes / 60) * 10) / 10 })
            ) : (
              <Link href="/settings/goals" className={linkClass}>
                {t("setWeekly")}
              </Link>
            )}
          </p>
        </div>
        <div className="flex flex-col gap-2 bg-paper-2 p-5">
          <p className="text-sm text-ink">{t("next")}</p>
          {path.next_lesson ? (
            <>
              <p className="font-medium text-ink">{path.next_lesson.title}</p>
              <p className="text-sm">{path.next_lesson.topic}</p>
              <Link href={`/learn/${path.next_lesson.slug}`} className={buttonVariants({ size: "sm", className: "mt-1 self-start" })}>
                {t("continue")}
                <ArrowRight aria-hidden />
              </Link>
            </>
          ) : (
            <p>{t("allDone")}</p>
          )}
        </div>
      </section>

      <p className="max-w-[68ch] text-sm">{t("legend")}</p>

      <ol className="flex flex-col gap-10">
        {stages.map(({ stage, nodes }) => (
          <li key={stage} className="flex flex-col gap-4">
            <h2 className="text-[length:var(--text-title)]">{t("stage", { stage })}</h2>
            <ol className="flex flex-col gap-3">
              {nodes.map((node) => (
                <TopicRow key={node.topic.slug} node={node} titles={titles} startOpen={node.topic.slug === nextTopic} />
              ))}
            </ol>
          </li>
        ))}
      </ol>

      <Versions />
    </div>
  );
}

function TopicRow({ node, titles, startOpen }: { node: PathNode; titles: Map<string, string>; startOpen: boolean }) {
  const t = useTranslations("roadmap");
  const [open, setOpen] = useState(startOpen);
  const panelId = useId();
  const review = node.recommendation === "review";
  const missing = node.requires.filter((slug) => titles.has(slug)).map((slug) => titles.get(slug));

  return (
    <li className="grid gap-3 md:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] md:gap-6">
      <RoadmapNodeCard
        title={node.topic.title}
        status={node.status}
        statusLabel={t(`status.${node.status}`)}
        percent={node.percent}
        review={review ? { label: t("reviewChip") } : undefined}
        className={cn("md:self-start", startOpen && "ring-2 ring-accent ring-offset-2 ring-offset-paper")}
      />
      <div className="flex min-w-0 flex-col gap-2">
        <p className="text-sm text-ink">{t(`reason.${node.reason.key}`, node.reason.params)}</p>
        <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
          <span>{node.topic.domain.name}</span>
          {node.status === "locked" && missing.length ? <span>{t("needs", { topics: missing.join(", ") })}</span> : null}
        </p>
        <button
          type="button"
          aria-expanded={open}
          aria-controls={panelId}
          onClick={() => setOpen((v) => !v)}
          className="inline-flex items-center gap-1.5 self-start rounded-control py-1 text-sm font-medium text-ink hover:text-accent"
        >
          <ChevronDown aria-hidden className={cn("size-4 transition-transform duration-[var(--dur-short)]", open && "rotate-180")} />
          {t("lessons", { count: node.lessons.length })}
        </button>
        <ul id={panelId} hidden={!open} className="flex flex-col border-t border-rule">
          {node.lessons.map((lesson) => (
            <LessonRow key={lesson.slug} lesson={lesson} />
          ))}
        </ul>
      </div>
    </li>
  );
}

function LessonRow({ lesson }: { lesson: PathLesson }) {
  const t = useTranslations("roadmap");
  const Icon = lesson.status === "completed" ? Check : lesson.status === "started" ? CircleDot : Circle;
  return (
    <li className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-rule py-2.5 text-sm">
      <Icon aria-hidden className={cn("size-4 shrink-0", lesson.status === "completed" ? "text-accent" : "text-muted")} />
      <Link href={`/learn/${lesson.slug}`} className={cn(linkClass, "min-w-0 flex-1")}>
        {lesson.title}
      </Link>
      <span className="sr-only">{t(`lessonStatus.${lesson.status}`)}</span>
      {lesson.is_required ? null : <span className="rounded-chip border border-rule px-1.5">{t("optional")}</span>}
      <Duration minutes={lesson.estimated_minutes} />
    </li>
  );
}

function Versions() {
  const t = useTranslations("roadmap");
  const format = useFormatter();
  const versions = usePathVersions();
  const earlier = (versions.data ?? []).filter((v) => v.status === "archived");
  if (!earlier.length) return null;
  return (
    <section aria-labelledby="versions-heading" className="flex max-w-[44rem] flex-col gap-3 border-t border-rule pt-8">
      <h2 id="versions-heading" className="text-[length:var(--text-lg)]">
        {t("versions")}
      </h2>
      <ul className="flex flex-col text-sm">
        {earlier.map((v) => (
          <li key={v.id} className="flex flex-wrap gap-x-5 gap-y-1 border-b border-rule py-2">
            <span className="measured text-ink">{t("version", { version: v.version })}</span>
            <span>{v.career.title}</span>
            <span>{format.dateTime(new Date(v.generated_at), { dateStyle: "medium" })}</span>
            <span>{v.assessment_score !== null ? t("basedOn", { score: v.assessment_score }) : t("noAssessment")}</span>
          </li>
        ))}
      </ul>
    </section>
  );
}
