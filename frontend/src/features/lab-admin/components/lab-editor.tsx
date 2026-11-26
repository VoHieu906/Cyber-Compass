"use client";

import { Archive, ArchiveRestore, RotateCcw, Save, Send } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useQueryClient } from "@tanstack/react-query";
import { useCallback, useEffect, useState } from "react";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { controlClasses } from "@/components/ui/input";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { LessonStatusBadge, UnpublishedChip } from "@/features/content-editor/components/labels";
import { ApiError } from "@/lib/api/client";
import { cn } from "@/lib/utils";
import { fetchAdminLab } from "../api";
import {
  labAdminKeys,
  useAdminLab,
  useAdminLabs,
  useArchiveLab,
  useCheckLab,
  useDiscardLabDraft,
  usePublishLab,
  useSaveLabDraft,
} from "../hooks";
import { cleanDefinition, countMessages, messagesAt, sameContent } from "../state";
import type { AdminLab, CheckReport, Impact, LabDefinition, PathMessages } from "../types";
import { CheckpointsPanel } from "./checkpoints-panel";
import { DetailsPanel } from "./details-panel";
import { HistoryPanel } from "./history-panel";
import { PathList } from "./parts";
import { PreviewPanel } from "./preview-panel";
import { ScenarioPanel } from "./scenario-panel";
import { SolutionsPanel } from "./solutions-panel";

const TABS = ["details", "scenario", "checkpoints", "solutions", "preview", "history"] as const;
type Tab = (typeof TABS)[number];

const DETAIL_FIELDS = ["title", "summary", "briefing", "domain", "lesson", "difficulty", "estimated_minutes", "engine"];

/** Where each server problem is fixed, to count problems on the tabs. */
const tabOf = (errors: PathMessages, tab: Tab): PathMessages => {
  if (tab === "details") return messagesAt(errors, ...DETAIL_FIELDS);
  if (tab === "checkpoints") return messagesAt(errors, "scenario.checkpoints");
  if (tab === "solutions") return messagesAt(errors, "solutions");
  if (tab === "scenario")
    return Object.fromEntries(
      Object.entries(messagesAt(errors, "scenario")).filter(([p]) => !p.startsWith("scenario.checkpoints")),
    );
  return {};
};

/** Admin → Labs → one lab (F7, Part B): details, scenario, checkpoints, solutions, preview and history. */
export function LabEditor({ id }: { id: number }) {
  const t = useTranslations("labAdmin");
  const lab = useAdminLab(id);

  if (lab.isError) {
    const missing = lab.error instanceof ApiError && lab.error.status === 404;
    return (
      <div className="flex flex-col items-start gap-3">
        <Alert tone="error">{missing ? t("editor.notFound") : t("editor.loadError")}</Alert>
        <Link href="/admin/labs" className={buttonVariants({ variant: "secondary" })}>
          {t("editor.back")}
        </Link>
      </div>
    );
  }
  if (!lab.data) return <div role="status" aria-label={t("editor.loading")} className="h-96 rounded-card bg-paper-3" />;
  return <Editor lab={lab.data} />;
}

function Editor({ lab }: { lab: AdminLab }) {
  const t = useTranslations("labAdmin.editor");
  const tTabs = useTranslations("labAdmin.tabs");
  const tc = useTranslations("common");
  const errorMessage = useErrorMessage();
  const engines = useAdminLabs().data?.meta.engines;
  const kinds = engines?.find((e) => e.key === lab.definition.engine)?.checkpoint_kinds ?? ["answer"];

  const [def, setDef] = useState<LabDefinition>(lab.definition);
  const [tab, setTab] = useState<Tab>("details");
  const [errors, setErrors] = useState<PathMessages>({});
  const [report, setReport] = useState<CheckReport | null>(null);
  const [badJson, setBadJson] = useState<ReadonlySet<string>>(new Set());
  const [conflict, setConflict] = useState(false);
  const [message, setMessage] = useState<{ tone: "error" | "success"; text: string } | null>(null);
  const [publishing, setPublishing] = useState(false);
  const [note, setNote] = useState("");
  const [confirm, setConfirm] = useState<Impact | null>(null);

  const save = useSaveLabDraft(lab.id);
  const publish = usePublishLab(lab.id);
  const discard = useDiscardLabDraft(lab.id);
  const archive = useArchiveLab();
  const check = useCheckLab(lab.id);
  const queryClient = useQueryClient();

  const dirty = !sameContent(def, lab.definition);
  const unreadable = badJson.size > 0;
  // A stable function: the JSON boxes clear their own key when they go away.
  const reportValidity = useCallback((key: string, invalid: boolean) => {
    setBadJson((prev) => {
      if (prev.has(key) === invalid) return prev;
      const next = new Set(prev);
      if (invalid) next.add(key);
      else next.delete(key);
      return next;
    });
  }, []);

  useEffect(() => {
    if (!dirty) return;
    const warn = (e: BeforeUnloadEvent) => e.preventDefault();
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);

  const change = (next: Partial<LabDefinition>) => setDef((d) => ({ ...d, ...next }));

  /** Shows what a failed request means for the editor: a conflict, problems by path, or a message. */
  function fail(error: unknown): boolean {
    if (error instanceof ApiError && error.status === 409) setConflict(true);
    else if (error instanceof ApiError && error.isValidation) {
      setErrors(error.fieldErrors);
      const first = TABS.find((x) => countMessages(tabOf(error.fieldErrors, x)) > 0);
      if (first) setTab(first);
    } else setMessage({ tone: "error", text: errorMessage(error) });
    return false;
  }

  async function saveDraft(): Promise<AdminLab | null> {
    setErrors({});
    setMessage(null);
    setConflict(false);
    try {
      const saved = await save.mutateAsync({ definition: cleanDefinition(def), lockVersion: lab.lock_version });
      setDef(saved.definition);
      setReport(null);
      return saved;
    } catch (error) {
      fail(error);
      return null;
    }
  }

  async function runSolutions() {
    setMessage(null);
    try {
      setReport(await check.mutateAsync(cleanDefinition(def)));
      setErrors({});
    } catch (error) {
      fail(error);
    }
  }

  /** Saves what is unsaved, then publishes; a restart that needs confirming asks first (D75). */
  async function doPublish(confirmRestart: boolean) {
    setMessage(null);
    let current: AdminLab | null = lab;
    if (dirty) current = await saveDraft();
    if (!current) {
      setPublishing(false);
      return;
    }
    try {
      await publish.mutateAsync({
        lock_version: current.lock_version,
        note: note.trim() || undefined,
        confirm_restart: confirmRestart || undefined,
      });
      setPublishing(false);
      setConfirm(null);
      setNote("");
      setReport(null);
      setMessage({ tone: "success", text: t("published") });
    } catch (error) {
      if (error instanceof ApiError && error.isValidation && error.fieldErrors.confirm_restart) {
        // The count of learners it touches comes from the server, fresh, not from this page's copy.
        const fresh = await fetchAdminLab(lab.id).catch(() => null);
        setPublishing(false);
        setConfirm(fresh?.impact ?? current.impact);
        return;
      }
      setPublishing(false);
      setConfirm(null);
      fail(error);
    }
  }

  async function reload() {
    const fresh = await fetchAdminLab(lab.id);
    // The cache entry is the latest copy: lock_version, impact and the definition move together.
    queryClient.setQueryData(labAdminKeys.lab(lab.id), fresh);
    setDef(fresh.definition);
    setErrors({});
    setConflict(false);
    setReport(null);
  }

  const status = lab.is_archived ? "archived" : lab.status;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-3">
        <Breadcrumb
          label={tc("breadcrumb")}
          items={[{ href: "/admin/labs", label: t("labs") }, { label: def.title || lab.slug }]}
        />
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex min-w-0 flex-col gap-2">
            <h1 className="text-[length:var(--text-2xl)] break-words">{def.title || lab.slug}</h1>
            <p className="flex flex-wrap items-center gap-2 text-sm">
              <LessonStatusBadge status={status} />
              {lab.has_draft && lab.status === "published" ? <UnpublishedChip /> : null}
              {dirty ? (
                <span className="rounded-chip border border-rule bg-paper-3 px-2 py-0.5 text-xs font-medium text-ink">
                  {t("unsaved")}
                </span>
              ) : null}
              <span>{lab.owned ? t("ownedDatabase") : t("ownedFile")}</span>
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button
              variant="secondary"
              onClick={() => void saveDraft()}
              loading={save.isPending}
              disabled={!dirty || unreadable}
            >
              <Save aria-hidden />
              {t("save")}
            </Button>
            <Button
              onClick={() => setPublishing(true)}
              disabled={unreadable || (!dirty && !lab.has_draft && lab.status === "published")}
            >
              <Send aria-hidden />
              {t("publish")}
            </Button>
            {lab.has_draft && lab.status === "published" ? (
              <Button
                variant="ghost"
                loading={discard.isPending}
                onClick={() =>
                  discard.mutate(undefined, {
                    onSuccess: (fresh) => {
                      setDef(fresh.definition);
                      setErrors({});
                      setReport(null);
                    },
                    onError: fail,
                  })
                }
              >
                <RotateCcw aria-hidden />
                {t("discard")}
              </Button>
            ) : null}
            <Button
              variant="ghost"
              loading={archive.isPending}
              onClick={() => archive.mutate({ id: lab.id, archived: !lab.is_archived })}
            >
              {lab.is_archived ? <ArchiveRestore aria-hidden /> : <Archive aria-hidden />}
              {lab.is_archived ? t("unarchive") : t("archive")}
            </Button>
          </div>
        </div>
      </div>

      {conflict ? (
        <Alert tone="error">
          <span className="flex flex-wrap items-center justify-between gap-3">
            <span>{t("conflict")}</span>
            <Button size="sm" variant="secondary" onClick={() => void reload()}>
              {t("reload")}
            </Button>
          </span>
        </Alert>
      ) : null}
      {message ? <Alert tone={message.tone}>{message.text}</Alert> : null}
      {unreadable ? <Alert tone="info">{t("unreadable")}</Alert> : null}
      {countMessages(errors) > 0 ? (
        <PathList messages={errors} label={t("problems", { count: countMessages(errors) })} />
      ) : null}

      <div role="tablist" aria-label={t("sections")} className="flex gap-1 overflow-x-auto border-b border-rule">
        {TABS.map((x) => {
          const problems = countMessages(tabOf(errors, x));
          return (
            <button
              key={x}
              role="tab"
              id={`lab-tab-${x}`}
              aria-selected={tab === x}
              aria-controls={`lab-panel-${x}`}
              tabIndex={tab === x ? 0 : -1}
              onClick={() => setTab(x)}
              onKeyDown={(e) => {
                const at = TABS.indexOf(x);
                const to =
                  e.key === "ArrowRight"
                    ? TABS[(at + 1) % TABS.length]
                    : e.key === "ArrowLeft"
                      ? TABS[(at + TABS.length - 1) % TABS.length]
                      : null;
                if (to) {
                  e.preventDefault();
                  setTab(to);
                  document.getElementById(`lab-tab-${to}`)?.focus();
                }
              }}
              className={cn(
                "-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-[color,border-color] duration-[var(--dur-micro)]",
                tab === x ? "border-attn text-ink" : "border-transparent text-muted hover:text-ink",
              )}
            >
              {tTabs(x)}
              {problems ? (
                <span className="rounded-chip bg-bad-soft px-1.5 text-xs text-bad">
                  <span className="sr-only">{t("problemsOn", { count: problems })}</span>
                  <span aria-hidden>{problems}</span>
                </span>
              ) : null}
            </button>
          );
        })}
      </div>

      <div
        role="tabpanel"
        id={`lab-panel-${tab}`}
        aria-labelledby={`lab-tab-${tab}`}
        tabIndex={0}
        className="min-w-0 focus-visible:outline-none"
      >
        {tab === "details" ? (
          <DetailsPanel def={def} slug={lab.slug} domains={lab.domains} errors={errors} onChange={change} />
        ) : null}
        {tab === "scenario" ? (
          <ScenarioPanel
            def={def}
            errors={errors}
            onChange={(scenario) => change({ scenario })}
            onValidity={reportValidity}
          />
        ) : null}
        {tab === "checkpoints" ? (
          <CheckpointsPanel
            def={def}
            kinds={kinds}
            errors={errors}
            onChange={(checkpoints) => change({ scenario: { ...def.scenario, checkpoints } })}
            onValidity={reportValidity}
          />
        ) : null}
        {tab === "solutions" ? (
          <SolutionsPanel
            solutions={def.solutions}
            errors={errors}
            report={report}
            checking={check.isPending}
            onChange={(solutions) => {
              setReport(null);
              change({ solutions });
            }}
            onValidity={reportValidity}
            onRun={() => void runSolutions()}
          />
        ) : null}
        {tab === "preview" ? <PreviewPanel id={lab.id} lockVersion={lab.lock_version} dirty={dirty} /> : null}
        {tab === "history" ? (
          <HistoryPanel
            id={lab.id}
            dirty={dirty}
            onRestored={(fresh) => {
              setDef(fresh.definition);
              setErrors({});
              setReport(null);
              setTab("details");
              setMessage({ tone: "success", text: t("restored") });
            }}
          />
        ) : null}
      </div>

      <Dialog
        open={publishing}
        onClose={() => setPublishing(false)}
        title={t("publishTitle")}
        description={t("publishBody")}
        closeLabel={tc("close")}
      >
        <div className="flex flex-col gap-4">
          {lab.impact.breaking && lab.impact.in_progress + lab.impact.completed > 0 ? (
            <Alert tone="info">
              {t("publishImpact", { progress: lab.impact.in_progress, completed: lab.impact.completed })}
            </Alert>
          ) : null}
          <label className="flex flex-col gap-1.5 text-sm font-medium text-ink">
            {t("note")}
            <input
              value={note}
              maxLength={300}
              onChange={(e) => setNote(e.target.value)}
              className={controlClasses(false)}
            />
          </label>
          <div className="flex flex-wrap justify-end gap-2">
            <Button variant="ghost" onClick={() => setPublishing(false)}>
              {tc("cancel")}
            </Button>
            <Button onClick={() => void doPublish(false)} loading={publish.isPending || save.isPending}>
              {t("publish")}
            </Button>
          </div>
        </div>
      </Dialog>

      <Dialog
        open={confirm !== null}
        onClose={() => setConfirm(null)}
        title={t("restartTitle")}
        description={t("restartBody", { progress: confirm?.in_progress ?? 0, completed: confirm?.completed ?? 0 })}
        closeLabel={tc("close")}
      >
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="ghost" onClick={() => setConfirm(null)}>
            {tc("cancel")}
          </Button>
          <Button variant="danger" onClick={() => void doPublish(true)} loading={publish.isPending}>
            {t("restartConfirm")}
          </Button>
        </div>
      </Dialog>
    </div>
  );
}
