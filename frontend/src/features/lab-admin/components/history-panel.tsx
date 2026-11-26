"use client";

import { History, RotateCcw } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { useLabRevisions, useRestoreLabRevision } from "../hooks";
import type { AdminLab, LabRevision } from "../types";

/** Published versions, newest first. Restoring copies one into the draft; learners see nothing until it is published. */
export function HistoryPanel({
  id,
  dirty,
  onRestored,
}: {
  id: number;
  dirty: boolean;
  onRestored: (lab: AdminLab) => void;
}) {
  const t = useTranslations("labAdmin.history");
  const tc = useTranslations("common");
  const format = useFormatter();
  const errorMessage = useErrorMessage();
  const revisions = useLabRevisions(id);
  const restore = useRestoreLabRevision(id);
  const [pick, setPick] = useState<LabRevision | null>(null);

  if (revisions.isError) return <Alert tone="error">{t("loadError")}</Alert>;
  if (!revisions.data) return <div aria-hidden className="h-40 rounded-card bg-paper-3" />;
  if (revisions.data.length === 0)
    return (
      <div className="panel flex flex-col items-start gap-2 p-6">
        <History aria-hidden className="size-6 text-muted" />
        <p>{t("empty")}</p>
      </div>
    );

  return (
    <div className="flex flex-col gap-4">
      {dirty ? <Alert tone="info">{t("unsaved")}</Alert> : null}
      {restore.isError ? <Alert tone="error">{errorMessage(restore.error)}</Alert> : null}
      <ol className="panel divide-y divide-rule">
        {revisions.data.map((r) => (
          <li key={r.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div className="flex min-w-0 flex-col gap-0.5">
              <p className="font-medium text-ink">{t("version", { version: r.version })}</p>
              <p className="text-sm">
                {format.dateTime(new Date(r.created_at), { dateStyle: "medium", timeStyle: "short" })}
              </p>
              {r.note ? <p className="text-sm">{r.note}</p> : null}
            </div>
            <Button variant="secondary" size="sm" onClick={() => setPick(r)}>
              <RotateCcw aria-hidden />
              {t("restore")}
            </Button>
          </li>
        ))}
      </ol>
      <Dialog
        open={pick !== null}
        onClose={() => setPick(null)}
        title={t("restoreTitle", { version: pick?.version ?? 0 })}
        description={t("restoreBody")}
        closeLabel={tc("close")}
      >
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="ghost" onClick={() => setPick(null)}>
            {tc("cancel")}
          </Button>
          <Button
            loading={restore.isPending}
            onClick={() =>
              pick &&
              restore.mutate(pick.version, {
                onSuccess: (lab) => {
                  setPick(null);
                  onRestored(lab);
                },
              })
            }
          >
            {t("restore")}
          </Button>
        </div>
      </Dialog>
    </div>
  );
}
