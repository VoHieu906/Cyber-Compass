"use client";

import { History, RotateCcw } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { useRevisions, type DocKind } from "../hooks";
import type { EditorDoc, Revision } from "../types";
import { useDocText, type DocText } from "../doc-text";

/** Published versions, newest first. Restoring copies a version into the draft; learners see nothing until it is published. */
export function RevisionHistory({
  kind,
  messages,
  lesson,
  dirty,
  busy,
  onRestore,
}: {
  kind: DocKind;
  /** This document type's wording for the history; missing keys come from content.history. */
  messages: string;
  lesson: EditorDoc;
  dirty: boolean;
  busy: boolean;
  onRestore: (version: number) => void;
}) {
  const t = useDocText(messages, "content.history");
  const tCommon = useTranslations("common");
  const format = useFormatter();
  const revisions = useRevisions(kind, lesson.id, true);
  const [pick, setPick] = useState<Revision | null>(null);

  if (revisions.isError) return <Alert tone="error">{t("loadError")}</Alert>;
  if (!revisions.data) return <div aria-hidden className="h-48 rounded-card bg-paper-3" />;
  if (revisions.data.length === 0) {
    return (
      <div className="panel flex flex-col items-start gap-2 p-6">
        <History aria-hidden className="size-5 text-muted" />
        <p>{t("empty")}</p>
      </div>
    );
  }

  return (
    <>
      <p className="max-w-[44rem] text-sm">{t("intro")}</p>
      <ol className="panels grid-cols-1">
        {revisions.data.map((r, i) => (
          <li
            key={r.version}
            className="flex flex-col gap-2 p-5 sm:flex-row sm:items-start sm:justify-between sm:gap-6"
          >
            <div className="flex min-w-0 flex-col gap-1">
              <p className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span className="measured text-sm text-ink">v{r.version}</span>
                <span className="font-medium text-ink">{r.title}</span>
                {i === 0 && lesson.status === "published" ? (
                  <span className="text-xs text-muted">{t("live")}</span>
                ) : null}
              </p>
              <p className="text-sm">
                {r.author ?? t("unknownAuthor")}
                {r.created_at
                  ? ` · ${format.dateTime(new Date(r.created_at), { dateStyle: "medium", timeStyle: "short" })}`
                  : null}
              </p>
              {r.note ? <p className="text-sm text-ink-2">“{r.note}”</p> : null}
              <p className="text-sm text-muted">{describe(r, t)}</p>
            </div>
            <Button
              variant="secondary"
              size="sm"
              className="shrink-0 self-start"
              disabled={busy}
              onClick={() => setPick(r)}
            >
              <RotateCcw aria-hidden />
              {t("restore")}
            </Button>
          </li>
        ))}
      </ol>
      <Dialog
        open={pick !== null}
        onClose={() => setPick(null)}
        title={t("confirmTitle", { version: pick?.version ?? 0 })}
        description={t("confirmBody")}
        closeLabel={tCommon("close")}
      >
        {dirty ? <Alert tone="error">{t("dirtyWarning")}</Alert> : null}
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="ghost" onClick={() => setPick(null)}>
            {tCommon("cancel")}
          </Button>
          <Button
            onClick={() => {
              if (pick) onRestore(pick.version);
              setPick(null);
            }}
          >
            {t("restore")}
          </Button>
        </div>
      </Dialog>
    </>
  );
}

function describe(r: Revision, t: DocText): string {
  if (!r.changes) return "";
  if (r.changes.initial) return t("initial", { count: r.section_count });
  const parts: string[] = [];
  if (r.changes.fields.length > 0)
    parts.push(t("fields", { fields: r.changes.fields.map((f) => t(`field.${f}`)).join(", ") }));
  const s = r.changes.sections;
  if (s.added) parts.push(t("added", { count: s.added }));
  if (s.removed) parts.push(t("removed", { count: s.removed }));
  if (s.edited) parts.push(t("edited", { count: s.edited }));
  if (s.reordered) parts.push(t("reordered"));
  return parts.length > 0 ? parts.join(" · ") : t("noChanges");
}
