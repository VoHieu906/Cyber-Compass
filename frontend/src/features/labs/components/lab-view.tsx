"use client";

import { ArrowLeft, Check, Circle, Play, RotateCcw, Trophy } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { controlClasses } from "@/components/ui/input";
import { ProgressTrack } from "@/features/assessment/components/parts";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { DifficultyChip, Duration } from "@/features/catalog/components/labels";
import { Prose } from "@/features/learning/components/prose";
import { ApiError } from "@/lib/api/client";
import { cn } from "@/lib/utils";
import { goTo, useCompassPageItems } from "@/features/compass/page-items";
import { useLab, useLabAction, useResetLab, useStartLab } from "../hooks";
import type { Checkpoint, FirewallScenario, FirewallState, HttpScenario, HttpState, LabAction, LabActionResult, LabDetail, ShellScenario, ShellState } from "../types";
import { FirewallConsole } from "./firewall-console";
import { HttpConsole } from "./http-console";
import { DIFFICULTY, ENGINE_ICON } from "./labs-list";
import { Terminal } from "./terminal";

export function LabView({ slug }: { slug: string }) {
  const t = useTranslations("labs");
  const lab = useLab(slug);

  if (lab.isError) {
    const missing = lab.error instanceof ApiError && lab.error.status === 404;
    return (
      <div className="container-page flex max-w-[44rem] flex-col items-start gap-3 py-[var(--space-2xl)]">
        <Alert tone="error">{missing ? t("notFound") : t("loadError")}</Alert>
        <Link href="/labs" className={buttonVariants({ variant: "secondary" })}>
          <ArrowLeft aria-hidden />
          {t("backToLabs")}
        </Link>
      </div>
    );
  }
  if (!lab.data) return <div role="status" aria-label={t("loading")} className="min-h-[60vh]" />;

  return <Workspace lab={lab.data} />;
}

function Workspace({ lab }: { lab: LabDetail }) {
  const t = useTranslations();
  const Icon = ENGINE_ICON[lab.engine];
  const start = useStartLab(lab.slug);
  const errorMessage = useErrorMessage();

  return (
    <div className="container-page flex flex-col gap-8 py-[var(--space-xl)]">
      <header className="flex flex-col gap-4">
        <Breadcrumb label={t("common.breadcrumb")} items={[{ href: "/labs", label: t("labs.title") }, { label: lab.title }]} />
        <h1 className="text-[length:var(--text-display-s)]">{lab.title}</h1>
        <p className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
          <span className="inline-flex items-center gap-1.5 text-ink">
            <Icon aria-hidden className="size-4 text-accent" />
            {t(`labs.engines.${lab.engine}`)}
          </span>
          <span>{lab.domain.name}</span>
          <DifficultyChip difficulty={DIFFICULTY[lab.difficulty - 1]} />
          <Duration minutes={lab.estimated_minutes} className="text-ink" />
          {lab.lesson ? (
            <Link href={`/learn/${lab.lesson.slug}`} className="text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent">
              {t("labs.practises", { lesson: lab.lesson.title })}
            </Link>
          ) : null}
        </p>
      </header>

      <div className="max-w-[48rem] text-ink-2">
        <Prose text={lab.briefing} />
      </div>

      {lab.attempt ? (
        <Running lab={lab} />
      ) : (
        <div className="flex flex-col items-start gap-3">
          <Button onClick={() => start.mutate()} loading={start.isPending}>
            <Play aria-hidden />
            {t("labs.start")}
          </Button>
          {start.isError ? <Alert tone="error">{errorMessage(start.error)}</Alert> : null}
        </div>
      )}
    </div>
  );
}

function Running({ lab }: { lab: LabDetail }) {
  const t = useTranslations("labs");
  const act = useLabAction(lab.slug);
  const errorMessage = useErrorMessage();
  const [notice, setNotice] = useState<string | null>(null);
  const attempt = lab.attempt!;
  const tc = useTranslations("compass");
  const open = lab.checkpoints.some((c) => !c.passed);
  useCompassPageItems([
    { id: "tasks", label: tc("pageTasks"), run: () => goTo(document.getElementById("checkpoints-heading")) },
    {
      id: "console",
      label: tc("pageConsole"),
      run: () => goTo(document.querySelector<HTMLElement>("#lab-console :is(input, select, textarea):not(:disabled)") ?? document.getElementById("lab-console")),
    },
    ...(open
      ? [{ id: "next", label: tc("pageNextTask"), run: () => {
          const task = document.querySelector<HTMLElement>('li[data-passed="false"]');
          goTo(task?.querySelector<HTMLElement>("input:not(:disabled)") ?? task ?? null);
        } }]
      : []),
  ]);

  /** Sends one action with the version this page last saw; failures become a message, not a throw. */
  async function send(action: LabAction): Promise<LabActionResult | null> {
    setNotice(null);
    try {
      return await act.mutateAsync({ attemptId: attempt.id, version: attempt.version, action });
    } catch (error) {
      if (error instanceof ApiError && error.status === 409) setNotice(t("staleReloaded"));
      else if (error instanceof ApiError && error.status === 429) setNotice(t("tooFast"));
      else setNotice(errorMessage(error) || t("actionError"));
      return null;
    }
  }

  const engineConsole =
    lab.engine === "linux-shell" ? (
      <Terminal scenario={lab.scenario as ShellScenario} state={attempt.state as ShellState} busy={act.isPending} onCommand={(command) => send({ type: "command", command })} />
    ) : lab.engine === "firewall-rules" ? (
      <FirewallConsole scenario={lab.scenario as FirewallScenario} state={attempt.state as FirewallState} busy={act.isPending} onAction={send} />
    ) : (
      <HttpConsole scenario={lab.scenario as HttpScenario} state={attempt.state as HttpState} busy={act.isPending} onAction={send} />
    );

  return (
    // On lg+ the workspace fills the screen below the header, so tasks and console are readable together.
    <div className="grid grid-cols-[minmax(0,1fr)] gap-8 lg:h-[calc(100dvh-var(--header-height)-3rem)] lg:min-h-[32rem] lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)] lg:gap-[var(--gap-columns)]">
      <Checkpoints lab={lab} busy={act.isPending} onAnswer={(checkpoint, value) => send({ type: "answer", checkpoint, value })} />
      <div id="lab-console" className={cn("flex min-w-0 flex-col gap-3 lg:min-h-0", lab.engine !== "linux-shell" && "lg:-m-1 lg:overflow-y-auto lg:p-1")}>
        {notice ? (
          <Alert tone="error">{notice}</Alert>
        ) : null}
        {engineConsole}
      </div>
    </div>
  );
}

function Checkpoints({ lab, busy, onAnswer }: { lab: LabDetail; busy: boolean; onAnswer: (id: string, value: string) => Promise<LabActionResult | null> }) {
  const t = useTranslations("labs");
  const passed = lab.checkpoints.filter((c) => c.passed).length;
  const total = lab.checkpoints.length;
  const done = lab.attempt?.completed_at != null;
  const list = useRef<HTMLDivElement>(null);
  const seen = useRef(passed);

  // When a task passes, bring the next open one into view inside the list (never the page, never focus).
  useEffect(() => {
    const box = list.current;
    if (!box || passed <= seen.current) {
      seen.current = passed;
      return;
    }
    seen.current = passed;
    const next = box.querySelector<HTMLElement>('li[data-passed="false"]');
    if (!next) return;
    const top = next.getBoundingClientRect().top - box.getBoundingClientRect().top + box.scrollTop - 12;
    const reduce = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
    box.scrollTo?.({ top, behavior: reduce ? "auto" : "smooth" });
  }, [passed]);

  return (
    <aside aria-labelledby="checkpoints-heading" className="flex flex-col gap-4 lg:min-h-0">
      <div className="flex flex-col gap-2">
        <h2 id="checkpoints-heading" className="text-[length:var(--text-title)]">
          {t("tasks")}
        </h2>
        <p className="measured text-sm text-ink" aria-live="polite">
          {t("checkpoints", { passed, total })}
        </p>
        <ProgressTrack value={Math.round((passed / Math.max(1, total)) * 100)} />
      </div>
      {done ? (
        <p role="status" className="flex items-center gap-2 rounded-card border border-accent bg-accent-soft px-4 py-3 text-sm font-medium text-ink">
          <Trophy aria-hidden className="size-4 text-accent" />
          {passed === total ? t("complete") : t("completedBefore")}
        </p>
      ) : null}
      {/* Only the list scrolls on lg+; the padding keeps the edge fades and focus rings off the first and last items. */}
      <div
        ref={list}
        className="flex flex-col gap-4 lg:sticky-panel lg:-mx-1 lg:min-h-0 lg:flex-1 lg:px-1 lg:py-3 lg:[mask-image:linear-gradient(to_bottom,transparent,black_0.75rem,black_calc(100%-0.75rem),transparent)]"
      >
        <ol className="flex flex-col gap-3">
          {lab.checkpoints.map((c, i) => (
            <CheckpointItem key={c.id} checkpoint={c} number={i + 1} busy={busy} onAnswer={onAnswer} />
          ))}
        </ol>
        <ResetButton lab={lab} />
      </div>
    </aside>
  );
}

function CheckpointItem({ checkpoint, number, busy, onAnswer }: { checkpoint: Checkpoint; number: number; busy: boolean; onAnswer: (id: string, value: string) => Promise<LabActionResult | null> }) {
  const t = useTranslations("labs");
  const [value, setValue] = useState("");
  const [wrong, setWrong] = useState(false);
  const inputId = `answer-${checkpoint.id}`;

  return (
    <li data-passed={checkpoint.passed} className="flex flex-col gap-2 rounded-card border border-rule bg-paper-2 p-4">
      <p className="flex gap-2.5 text-sm text-ink">
        {checkpoint.passed ? <Check aria-hidden className="mt-0.5 size-4 shrink-0 text-accent" /> : <Circle aria-hidden className="mt-0.5 size-4 shrink-0 text-muted" />}
        <span>
          <span className="sr-only">
            {t("taskNumber", { number })}, {checkpoint.passed ? t("done") : t("notDone")}:{" "}
          </span>
          {checkpoint.title}
        </span>
      </p>
      {checkpoint.kind === "answer" && !checkpoint.passed ? (
        <form
          className="flex flex-col gap-2 pl-6"
          onSubmit={async (e) => {
            e.preventDefault();
            if (!value.trim()) return;
            const result = await onAnswer(checkpoint.id, value);
            if (result) setWrong(result.output.correct === false);
          }}
        >
          <label htmlFor={inputId} className="sr-only">
            {t("yourAnswer")}
          </label>
          <div className="flex gap-2">
            <input
              id={inputId}
              value={value}
              onChange={(e) => {
                setValue(e.target.value);
                setWrong(false);
              }}
              maxLength={200}
              autoComplete="off"
              spellCheck={false}
              aria-invalid={wrong || undefined}
              aria-describedby={wrong ? `${inputId}-wrong` : undefined}
              className={controlClasses(wrong, "measured h-9 text-sm")}
            />
            <Button type="submit" size="sm" variant="secondary" disabled={busy}>
              {t("check")}
            </Button>
          </div>
          {wrong ? (
            <p id={`${inputId}-wrong`} role="status" className="text-sm text-bad">
              {t("notQuite")}
            </p>
          ) : null}
        </form>
      ) : null}
      {checkpoint.hint && !checkpoint.passed ? (
        <details className="pl-6 text-sm">
          <summary className="cursor-pointer text-ink underline decoration-rule-2 underline-offset-4">{t("hint")}</summary>
          <p className="mt-1.5">{checkpoint.hint}</p>
        </details>
      ) : null}
    </li>
  );
}

function ResetButton({ lab }: { lab: LabDetail }) {
  const t = useTranslations();
  const reset = useResetLab(lab.slug);
  const [open, setOpen] = useState(false);
  const attempt = lab.attempt!;

  return (
    <>
      <Button variant="ghost" size="sm" className="self-start" onClick={() => setOpen(true)}>
        <RotateCcw aria-hidden />
        {t("labs.reset")}
      </Button>
      <Dialog open={open} onClose={() => setOpen(false)} title={t("labs.resetTitle")} description={t("labs.resetBody")} closeLabel={t("common.close")}>
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="ghost" onClick={() => setOpen(false)}>
            {t("common.cancel")}
          </Button>
          <Button
            variant="danger"
            loading={reset.isPending}
            onClick={() => reset.mutate({ attemptId: attempt.id, version: attempt.version }, { onSettled: () => setOpen(false) })}
          >
            {t("labs.reset")}
          </Button>
        </div>
      </Dialog>
    </>
  );
}
