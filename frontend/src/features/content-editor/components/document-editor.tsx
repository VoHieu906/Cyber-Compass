"use client";

import { Archive, ArchiveRestore, ExternalLink, Plus, RotateCcw, Save, Send, Trash2 } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useId, useRef, useState, type ComponentType, type ReactNode } from "react";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { controlClasses } from "@/components/ui/input";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { ApiError } from "@/lib/api/client";
import { cn } from "@/lib/utils";
import { useShortcut } from "@/lib/shortcuts/use-shortcut";
import { countErrors, moveItem, newSection, sameCopy, type FieldErrors } from "../editor-state";
import { useDocText, type DocText } from "../doc-text";
import { useBlockTypes, useDocActions, useEditorDoc, type DocKind, type DocOf } from "../hooks";
import type { BlockTypeInfo, EditorSection } from "../types";
import { LessonStatusBadge, UnpublishedChip } from "./labels";
import { RevisionHistory } from "./revision-history";
import { SectionCard } from "./section-card";

type Tab = "edit" | "preview" | "history";
type Confirm = "publish" | "discard" | "archive" | "unarchive" | "delete" | "remove-referenced" | null;

export type DetailsProps<K extends DocKind> = {
  copy: DocOf<K>["working_copy"];
  doc: DocOf<K>;
  errors: FieldErrors;
  onChange: (patch: Partial<DocOf<K>["working_copy"]>) => void;
  t: DocText;
};

/**
 * What differs between the document types the editor serves (D55). Everything else (blocks,
 * saving, locking, publishing, history) is the one editor.
 */
export type EditorAdapter<K extends DocKind> = {
  kind: K;
  /** Message namespaces with this type's wording; missing keys fall back to content.editor and content.history. */
  messages: string;
  historyMessages: string;
  listHref: string;
  liveUrl: (doc: DocOf<K>) => string | null;
  subtitle?: (doc: DocOf<K>) => ReactNode;
  Details: ComponentType<DetailsProps<K>>;
  Preview: ComponentType<{ doc: DocOf<K>; dirty: boolean; t: DocText }>;
  /** Exercise questions per section id (lessons, D30): removing such a block asks first. */
  references?: (doc: DocOf<K>) => Record<string, string[]>;
  /** The message key of an extra note in the publish dialog, or null. */
  publishNotice?: (doc: DocOf<K>) => string | null;
  /** Posts come back from the archive as they were; lessons are published again. */
  canUnarchive?: boolean;
};

export function DocumentEditor<K extends DocKind>({ adapter, id }: { adapter: EditorAdapter<K>; id: number }) {
  const t = useDocText(adapter.messages);
  const lesson = useEditorDoc(adapter.kind, id);
  const blockTypes = useBlockTypes();

  if (lesson.isError) {
    const missing = lesson.error instanceof ApiError && lesson.error.status === 404;
    return (
      <div className="flex flex-col items-start gap-3">
        <Alert tone="error">{missing ? t("notFound") : t("loadError")}</Alert>
        <Link href={adapter.listHref} className={buttonVariants({ variant: "secondary", size: "sm" })}>
          {t("backToLessons")}
        </Link>
      </div>
    );
  }
  if (!lesson.data || !blockTypes.data) return <div aria-hidden className="h-[70vh] rounded-card bg-paper-3" />;

  return (
    <EditorForm
      key={lesson.data.id}
      adapter={adapter}
      lesson={lesson.data as DocOf<K>}
      blockTypes={blockTypes.data}
      refetch={async () => ({ data: (await lesson.refetch()).data as DocOf<K> | undefined })}
    />
  );
}

let keySeed = 0;
const newKey = () => `new-${++keySeed}`;

/**
 * The editing session. The form holds the working copy; nothing reaches learners until
 * Publish. Every save sends lock_version, so two admins can't overwrite each other.
 */
function EditorForm<K extends DocKind>({
  adapter,
  lesson,
  blockTypes,
  refetch,
}: {
  adapter: EditorAdapter<K>;
  lesson: DocOf<K>;
  blockTypes: BlockTypeInfo[];
  refetch: () => Promise<{ data?: DocOf<K> }>;
}) {
  type Doc = DocOf<K>;
  type Copy = Doc["working_copy"];
  const t = useDocText(adapter.messages);
  const tCommon = useTranslations("common");
  const format = useFormatter();
  const router = useRouter();
  const errorMessage = useErrorMessage();

  const [copy, setCopy] = useState<Copy>(lesson.working_copy);
  const [base, setBase] = useState<Copy>(lesson.working_copy);
  const [keys, setKeys] = useState<string[]>(() => lesson.working_copy.sections.map((s) => s.id ?? newKey()));
  const [errors, setErrors] = useState<FieldErrors>({});
  const [conflict, setConflict] = useState(false);
  const [badJson, setBadJson] = useState<Set<string>>(new Set());
  const [tab, setTab] = useState<Tab>("edit");
  const [confirm, setConfirm] = useState<Confirm>(null);
  // The block waiting for confirmation because exercise questions use it (D30).
  const [removing, setRemoving] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const { save, publish, discard, archive, unarchive, remove, restore } = useDocActions(adapter.kind, lesson.id);
  const references = adapter.references?.(lesson) ?? {};

  const dirty = !sameCopy(copy, base);
  // Latest values for async callbacks: a save can finish after further edits.
  const copyRef = useRef(copy);
  const keysRef = useRef(keys);
  const savingRef = useRef(false);
  useEffect(() => {
    copyRef.current = copy;
    keysRef.current = keys;
  }, [copy, keys]);
  const typeByKey = Object.fromEntries(blockTypes.map((b) => [b.key, b]));
  const busy =
    save.isPending ||
    publish.isPending ||
    discard.isPending ||
    archive.isPending ||
    unarchive.isPending ||
    restore.isPending ||
    remove.isPending;

  /** Adopt a lesson returned by the server as the new baseline. */
  const load = useCallback((next: Doc) => {
    setCopy(next.working_copy);
    setBase(next.working_copy);
    setKeys(next.working_copy.sections.map((s) => s.id ?? newKey()));
    setErrors({});
    setBadJson(new Set());
    setConflict(false);
  }, []);

  const handleError = useCallback(
    (error: unknown) => {
      if (error instanceof ApiError && error.status === 409) return setConflict(true);
      if (error instanceof ApiError && error.isValidation) {
        setErrors(error.fieldErrors);
        setTab("edit");
        return;
      }
      setActionError(errorMessage(error));
    },
    [errorMessage],
  );

  /**
   * A finished save becomes the new baseline. If the author kept typing while it was in
   * flight, their newer edits stay in the form (still unsaved); only the ids the server
   * gave to new blocks are taken over, so the next save updates those blocks instead of
   * creating them again.
   */
  const adoptSaved = useCallback((saved: Doc, sentCopy: Copy, sentKeys: string[]) => {
    const idByKey = new Map(sentKeys.map((key, i) => [key, saved.working_copy.sections[i]?.id]));
    setBase(saved.working_copy);
    setConflict(false);
    if (copyRef.current === sentCopy) {
      setCopy(saved.working_copy);
      setErrors({});
      return;
    }
    setCopy((current) => ({
      ...current,
      sections: current.sections.map((s, i) => (s.id ? s : { ...s, id: idByKey.get(keysRef.current[i]) ?? s.id })),
    }));
  }, []);

  const doSave = useCallback(async (): Promise<Doc | null> => {
    if (savingRef.current) return null; // one save at a time: a second would carry a stale lock_version
    if (badJson.size > 0) {
      setActionError(t("fixJsonFirst"));
      return null;
    }
    setActionError(null);
    setNotice(null);
    savingRef.current = true;
    const sentCopy = copy;
    const sentKeys = keys;
    try {
      const saved = (await save.mutateAsync({ copy: sentCopy, lockVersion: lesson.lock_version })) as Doc;
      adoptSaved(saved, sentCopy, sentKeys);
      setNotice(t("saved"));
      return saved;
    } catch (error) {
      handleError(error);
      return null;
    } finally {
      savingRef.current = false;
    }
  }, [adoptSaved, badJson.size, copy, handleError, keys, lesson.lock_version, save, t]);

  // Ctrl/Cmd+S saves (the registry's editor.save, D52); leaving with unsaved work asks first.
  const saveRef = useRef(doSave);
  useEffect(() => {
    saveRef.current = doSave;
  }, [doSave]);
  useShortcut("editor.save", () => void saveRef.current());
  useEffect(() => {
    if (!dirty) return;
    const onLeave = (e: BeforeUnloadEvent) => e.preventDefault();
    window.addEventListener("beforeunload", onLeave);
    return () => window.removeEventListener("beforeunload", onLeave);
  }, [dirty]);

  // Errors are tied to positions; once blocks move, old messages would point at the wrong block.
  const clearErrors = (matches: (key: string) => boolean) =>
    setErrors((e) => {
      const kept = Object.entries(e).filter(([k]) => !matches(k));
      return kept.length === Object.keys(e).length ? e : Object.fromEntries(kept);
    });
  const clearSectionErrors = () => clearErrors((k) => k === "sections" || k.startsWith("sections."));
  const setSections = (sections: EditorSection[], nextKeys: string[]) => {
    setCopy((c) => ({ ...c, sections }));
    setKeys(nextKeys);
  };
  const setJsonValidity = (key: string, invalid: boolean) =>
    setBadJson((prev) => {
      if (prev.has(key) === invalid) return prev;
      const next = new Set(prev);
      if (invalid) next.add(key);
      else next.delete(key);
      return next;
    });
  const removeSection = (i: number) => {
    clearSectionErrors();
    setJsonValidity(keys[i], false);
    setSections(
      copy.sections.filter((_, j) => j !== i),
      keys.filter((_, j) => j !== i),
    );
  };

  const errorCount = countErrors(errors);
  const learnerUrl = adapter.liveUrl(lesson);

  return (
    <div className="flex flex-col gap-6">
      <Breadcrumb
        items={[{ href: adapter.listHref, label: t("lessons") }, { label: base.title || t("untitled") }]}
        label={tCommon("breadcrumb")}
      />

      <header className="flex flex-col gap-3">
        <h1 className="text-[length:var(--text-2xl)]">{base.title || t("untitled")}</h1>
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
          <LessonStatusBadge status={lesson.status} />
          {lesson.has_unpublished_changes ? <UnpublishedChip /> : null}
          {adapter.subtitle ? <span>{adapter.subtitle(lesson)}</span> : null}
          {lesson.draft?.updated_at ? (
            <span className="text-muted">
              {t("draftSavedBy", {
                name: lesson.draft.updated_by ?? "?",
                time: format.dateTime(new Date(lesson.draft.updated_at), { dateStyle: "medium", timeStyle: "short" }),
              })}
            </span>
          ) : null}
        </div>
      </header>

      {/* The action bar stays in reach while scrolling a long lesson. */}
      <div className="sticky top-[var(--header-height)] z-[var(--z-sticky-nav)] -mx-[var(--gutter)] flex flex-wrap items-center justify-between gap-3 border-y border-rule bg-[color-mix(in_oklab,var(--color-paper)_94%,transparent)] px-[var(--gutter)] py-3 backdrop-blur-[6px] lg:mx-0 lg:rounded-card lg:border lg:px-4">
        <div role="tablist" aria-label={t("views")} className="flex gap-1">
          {(["edit", "preview", "history"] as const).map((v) => (
            <button
              key={v}
              type="button"
              role="tab"
              aria-selected={tab === v}
              onClick={() => setTab(v)}
              className={cn(
                "rounded-control px-3 py-1.5 text-sm font-medium transition-[color,background-color] duration-[var(--dur-micro)]",
                tab === v ? "bg-paper-3 text-ink" : "text-muted hover:text-ink",
              )}
            >
              {t(`tabs.${v}`)}
            </button>
          ))}
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm text-muted" aria-live="polite">
            {dirty ? t("unsaved") : lesson.has_unpublished_changes ? t("savedNotPublished") : t("upToDate")}
          </span>
          <Button
            variant={dirty ? "primary" : "secondary"}
            size="sm"
            onClick={() => void doSave()}
            loading={save.isPending}
            disabled={busy || !dirty}
            title={t("saveShortcut")}
          >
            <Save aria-hidden />
            {t("save")}
          </Button>
          <Button
            variant={dirty ? "secondary" : "primary"}
            size="sm"
            onClick={() => setConfirm("publish")}
            disabled={busy || (!dirty && !lesson.has_unpublished_changes && lesson.status === "published")}
          >
            <Send aria-hidden />
            {lesson.status === "archived" ? t("republish") : t("publish")}
          </Button>
        </div>
      </div>

      {conflict ? (
        <div className="flex flex-col items-start gap-3">
          <Alert tone="error">{t("conflict")}</Alert>
          <Button
            variant="secondary"
            size="sm"
            onClick={async () => {
              const fresh = await refetch();
              if (fresh.data) load(fresh.data);
            }}
          >
            <RotateCcw aria-hidden />
            {t("loadLatest")}
          </Button>
        </div>
      ) : null}
      {errorCount > 0 ? (
        <Alert tone="error">
          {t("errorSummary", { count: errorCount })}
          {errors.sections ? ` ${errors.sections[0]}` : null}
        </Alert>
      ) : null}
      {actionError ? <Alert tone="error">{actionError}</Alert> : null}
      {notice && !dirty ? <Alert tone="success">{notice}</Alert> : null}

      {tab === "edit" ? (
        <div className="flex flex-col gap-10">
          <adapter.Details
            copy={copy}
            doc={lesson}
            t={t}
            errors={errors}
            onChange={(patch) => {
              setCopy((c) => ({ ...c, ...patch }));
              clearErrors((key) => Object.keys(patch).some((field) => key === field || key.startsWith(`${field}.`)));
            }}
          />

          <section aria-labelledby="blocks-heading" className="flex flex-col gap-4">
            <div className="flex items-baseline justify-between gap-3">
              <h2 id="blocks-heading" className="text-[length:var(--text-lg)]">
                {t("blocks")}
              </h2>
              <span className="measured text-sm text-muted">{t("blockCount", { count: copy.sections.length })}</span>
            </div>
            {copy.sections.length === 0 ? <p className="text-sm">{t("noBlocks")}</p> : null}
            <ol className="flex flex-col gap-4">
              {copy.sections.map((section, i) => (
                <SectionCard
                  key={keys[i]}
                  section={section}
                  index={i}
                  count={copy.sections.length}
                  blockType={typeByKey[section.type]}
                  errors={errors}
                  onChange={(next) => {
                    setCopy((c) => ({ ...c, sections: c.sections.map((s, j) => (j === i ? next : s)) }));
                    // The block's messages describe the version that was saved; editing it retires them.
                    clearErrors((key) => key.startsWith(`sections.${i}.`));
                  }}
                  onMove={(to) => {
                    clearSectionErrors();
                    setSections(moveItem(copy.sections, i, to), moveItem(keys, i, to));
                  }}
                  usedBy={section.id ? references[section.id] : undefined}
                  onRemove={() => {
                    if (section.id && references[section.id]?.length) {
                      setRemoving(i);
                      setConfirm("remove-referenced");
                    } else {
                      removeSection(i);
                    }
                  }}
                  onJsonValidity={(invalid) => setJsonValidity(keys[i], invalid)}
                />
              ))}
            </ol>
            <AddBlock
              blockTypes={blockTypes}
              onAdd={(type) => {
                clearSectionErrors();
                setSections([...copy.sections, newSection(type)], [...keys, newKey()]);
                requestAnimationFrame(() =>
                  document
                    .getElementById(`block-${copy.sections.length}`)
                    ?.scrollIntoView({ behavior: "smooth", block: "start" }),
                );
              }}
            />
          </section>

          <section aria-labelledby="danger-heading" className="flex flex-col gap-3 border-t border-rule pt-6">
            <h2 id="danger-heading" className="text-base font-medium text-ink">
              {t("moreActions")}
            </h2>
            <div className="flex flex-wrap gap-2">
              {learnerUrl ? (
                <Link href={learnerUrl} target="_blank" className={buttonVariants({ variant: "ghost", size: "sm" })}>
                  <ExternalLink aria-hidden />
                  {t("viewLive")}
                </Link>
              ) : null}
              {lesson.has_unpublished_changes && lesson.published_at ? (
                <Button variant="ghost" size="sm" disabled={busy} onClick={() => setConfirm("discard")}>
                  <RotateCcw aria-hidden />
                  {t("discard")}
                </Button>
              ) : null}
              {lesson.status === "published" ? (
                <Button variant="ghost" size="sm" disabled={busy} onClick={() => setConfirm("archive")}>
                  <Archive aria-hidden />
                  {t("archive")}
                </Button>
              ) : null}
              {adapter.canUnarchive && lesson.status === "archived" ? (
                <Button variant="ghost" size="sm" disabled={busy} onClick={() => setConfirm("unarchive")}>
                  <ArchiveRestore aria-hidden />
                  {t("unarchive")}
                </Button>
              ) : null}
              {lesson.can_delete ? (
                <Button variant="danger" size="sm" disabled={busy} onClick={() => setConfirm("delete")}>
                  <Trash2 aria-hidden />
                  {t("delete")}
                </Button>
              ) : null}
            </div>
            {!lesson.can_delete ? <p className="text-sm text-muted">{t("whyNoDelete")}</p> : null}
          </section>
        </div>
      ) : null}

      {tab === "preview" ? <adapter.Preview doc={lesson} dirty={dirty} t={t} /> : null}

      {tab === "history" ? (
        <RevisionHistory
          kind={adapter.kind}
          messages={adapter.historyMessages}
          lesson={lesson}
          dirty={dirty}
          busy={busy}
          onRestore={(version) =>
            restore.mutate(
              { version, lockVersion: lesson.lock_version },
              {
                onSuccess: (next) => {
                  load(next as Doc);
                  setNotice(t("restored", { version }));
                  setTab("edit");
                },
                onError: handleError,
              },
            )
          }
        />
      ) : null}

      <PublishDialog
        open={confirm === "publish"}
        onClose={() => setConfirm(null)}
        dirty={dirty}
        archived={lesson.status === "archived"}
        notice={adapter.publishNotice?.(lesson) ?? null}
        t={t}
        pending={save.isPending || publish.isPending}
        onPublish={async (note) => {
          const current = dirty ? await doSave() : lesson;
          if (!current) return setConfirm(null);
          publish.mutate(
            { lockVersion: current.lock_version, note },
            {
              onSuccess: (next) => {
                load(next as Doc);
                setNotice(t("published"));
                setConfirm(null);
              },
              onError: (e) => {
                setConfirm(null);
                handleError(e);
              },
            },
          );
        }}
      />
      <ConfirmDialog
        open={confirm === "remove-referenced"}
        onClose={() => setConfirm(null)}
        title={t("removeReferencedTitle")}
        body={t("removeReferencedBody", {
          questions: removing !== null ? (references[copy.sections[removing]?.id ?? ""] ?? []).join(", ") : "",
        })}
        action={t("removeReferenced")}
        danger
        pending={false}
        onConfirm={() => {
          if (removing !== null) removeSection(removing);
          setRemoving(null);
          setConfirm(null);
        }}
      />
      <ConfirmDialog
        open={confirm === "discard"}
        onClose={() => setConfirm(null)}
        title={t("discardTitle")}
        body={t("discardBody")}
        action={t("discard")}
        pending={discard.isPending}
        onConfirm={() =>
          discard.mutate(undefined, {
            onSuccess: (next) => {
              load(next as Doc);
              setNotice(t("discarded"));
              setConfirm(null);
            },
            onError: (e) => {
              setConfirm(null);
              handleError(e);
            },
          })
        }
      />
      <ConfirmDialog
        open={confirm === "archive"}
        onClose={() => setConfirm(null)}
        title={t("archiveTitle")}
        body={t("archiveBody")}
        action={t("archive")}
        pending={archive.isPending}
        onConfirm={() =>
          archive.mutate(undefined, {
            onSuccess: () => {
              setNotice(t("archived"));
              setConfirm(null);
            },
            onError: (e) => {
              setConfirm(null);
              handleError(e);
            },
          })
        }
      />
      <ConfirmDialog
        open={confirm === "unarchive"}
        onClose={() => setConfirm(null)}
        title={t("unarchiveTitle")}
        body={t("unarchiveBody")}
        action={t("unarchive")}
        pending={unarchive.isPending}
        onConfirm={() =>
          unarchive.mutate(undefined, {
            onSuccess: (next) => {
              load(next as Doc);
              setNotice(t("unarchived"));
              setConfirm(null);
            },
            onError: (e) => {
              setConfirm(null);
              handleError(e);
            },
          })
        }
      />
      <ConfirmDialog
        open={confirm === "delete"}
        onClose={() => setConfirm(null)}
        title={t("deleteTitle")}
        body={t("deleteBody")}
        action={t("delete")}
        danger
        pending={remove.isPending}
        onConfirm={() =>
          remove.mutate(undefined, {
            onSuccess: () => router.replace(adapter.listHref),
            onError: (e) => {
              setConfirm(null);
              handleError(e);
            },
          })
        }
      />
    </div>
  );
}

export function Labelled({
  id,
  label,
  error,
  hint,
  children,
}: {
  id: string;
  label: string;
  error?: string;
  hint?: string;
  children: ReactNode;
}) {
  return (
    <div className="flex min-w-0 flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {label}
      </label>
      {children}
      {error || hint ? <p className={cn("text-sm", error ? "text-bad" : "text-muted")}>{error ?? hint}</p> : null}
    </div>
  );
}

const BLOCK_GROUPS = [
  { key: "text", types: ["prose", "callout", "steps", "code", "media"] },
  {
    key: "diagrams",
    types: [
      "layer-stack",
      "sequence",
      "flow",
      "tree",
      "topology",
      "comparison",
      "timeline",
      "bitfield",
      "step-through",
      "diagram",
    ],
  },
  { key: "practice", types: ["knowledge-check", "calculator"] },
  { key: "threeD", types: ["scene-3d", "script-3d"] },
] as const;

function AddBlock({ blockTypes, onAdd }: { blockTypes: BlockTypeInfo[]; onAdd: (type: BlockTypeInfo) => void }) {
  const t = useTranslations("content.editor");
  const grouped = new Set<string>(BLOCK_GROUPS.flatMap((g) => g.types));
  const groups = [
    ...BLOCK_GROUPS.map((g) => ({
      key: g.key,
      types: g.types.map((k) => blockTypes.find((b) => b.key === k)).filter((b): b is BlockTypeInfo => Boolean(b)),
    })),
    { key: "other" as const, types: blockTypes.filter((b) => !grouped.has(b.key)) },
  ].filter((g) => g.types.length > 0);

  return (
    <div className="flex flex-col gap-4 rounded-card border border-dashed border-rule-2 p-4">
      <p className="flex items-center gap-2 text-sm font-medium text-ink">
        <Plus aria-hidden className="size-4" />
        {t("addBlock")}
      </p>
      {groups.map((group) => (
        <section key={group.key} className="flex flex-col gap-2" aria-labelledby={`add-${group.key}`}>
          <h3 id={`add-${group.key}`} className="text-xs font-medium text-muted">
            {t(`blockGroups.${group.key}`)}
          </h3>
          <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {group.types.map((type) => (
              <li key={type.key}>
                <button
                  type="button"
                  onClick={() => onAdd(type)}
                  className="flex h-full w-full flex-col gap-0.5 rounded-control border border-rule bg-paper-2 px-3 py-2.5 text-left transition-[border-color,background-color] duration-[var(--dur-micro)] hover:border-muted hover:bg-paper-3"
                >
                  <span className="text-sm font-medium text-ink">{type.label}</span>
                  <span className="text-xs">{type.description}</span>
                </button>
              </li>
            ))}
          </ul>
        </section>
      ))}
    </div>
  );
}

function PublishDialog({
  open,
  onClose,
  dirty,
  archived,
  notice,
  t,
  pending,
  onPublish,
}: {
  open: boolean;
  onClose: () => void;
  dirty: boolean;
  archived: boolean;
  notice: string | null;
  t: DocText;
  pending: boolean;
  onPublish: (note: string) => void;
}) {
  const tCommon = useTranslations("common");
  const noteId = useId();
  const [note, setNote] = useState("");

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={archived ? t("republishTitle") : t("publishTitle")}
      description={t("publishBody")}
      closeLabel={tCommon("close")}
    >
      <form
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault();
          onPublish(note);
        }}
      >
        {dirty ? <Alert tone="info">{t("publishSavesFirst")}</Alert> : null}
        {notice ? <Alert tone="info">{t(notice)}</Alert> : null}
        <div className="flex flex-col gap-1.5">
          <label htmlFor={noteId} className="text-sm font-medium text-ink">
            {t("note")} <span className="font-normal text-muted">({t("optional")})</span>
          </label>
          <input
            id={noteId}
            value={note}
            maxLength={300}
            onChange={(e) => setNote(e.target.value)}
            placeholder={t("notePlaceholder")}
            className={controlClasses(false)}
          />
        </div>
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            {tCommon("cancel")}
          </Button>
          <Button type="submit" loading={pending}>
            <Send aria-hidden />
            {archived ? t("republish") : t("publish")}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

function ConfirmDialog({
  open,
  onClose,
  title,
  body,
  action,
  danger,
  pending,
  onConfirm,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  body: string;
  action: string;
  danger?: boolean;
  pending: boolean;
  onConfirm: () => void;
}) {
  const tCommon = useTranslations("common");
  return (
    <Dialog open={open} onClose={onClose} title={title} description={body} closeLabel={tCommon("close")}>
      <div className="flex flex-wrap justify-end gap-2">
        <Button variant="ghost" onClick={onClose}>
          {tCommon("cancel")}
        </Button>
        <Button variant={danger ? "danger" : "primary"} loading={pending} onClick={onConfirm}>
          {action}
        </Button>
      </div>
    </Dialog>
  );
}
