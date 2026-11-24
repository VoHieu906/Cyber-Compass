"use client";

import { useMutation } from "@tanstack/react-query";
import { Download, PackageOpen } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { documentPackageUrl, exportDownloadUrl, startExport } from "../api";
import { useTransfer } from "../hooks";
import type { ExportSubject, Transfer } from "../types";

type Props = {
  open: boolean;
  onClose: () => void;
  subject: { type: ExportSubject["type"]; id: number };
  /** The document's blocks, to export only some (lessons and posts). */
  sections?: { id: string; label: string }[];
};

/**
 * Exports a lesson, a post or a whole course as a content package (F6, D67): a .ccpkg.json at
 * once (images stay references), or a .ccpkg.zip with its images, built on the worker.
 */
export function ExportDialog({ open, onClose, subject, sections }: Props) {
  const t = useTranslations("content.packages");
  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={t(`exportTitle.${subject.type}`)}
      description={t("exportIntro")}
      closeLabel={t("close")}
    >
      {open ? <ExportBody subject={subject} sections={sections} /> : null}
    </Dialog>
  );
}

function ExportBody({ subject, sections }: Pick<Props, "subject" | "sections">) {
  const t = useTranslations("content.packages");
  const errorMessage = useErrorMessage();
  const course = subject.type === "course";
  const [scope, setScope] = useState<"whole" | "chosen">("whole");
  const [chosen, setChosen] = useState<string[]>([]);
  const [format, setFormat] = useState<"json" | "zip">(course ? "zip" : "json");
  const [started, setStarted] = useState<Transfer | null>(null);
  const name = useId();

  const picked = scope === "chosen" ? chosen : undefined;
  const start = useMutation({ mutationFn: () => startExport({ ...subject, sections: picked }), onSuccess: setStarted });
  const ready = scope === "whole" || chosen.length > 0;

  if (started) return <ExportProgress initial={started} />;

  return (
    <div className="flex flex-col gap-5 text-sm">
      {!course && sections?.length ? (
        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 font-medium text-ink">{t("scope")}</legend>
          {(["whole", "chosen"] as const).map((s) => (
            <label key={s} className="flex items-center gap-2 text-ink">
              <input type="radio" name={`${name}-scope`} checked={scope === s} onChange={() => setScope(s)} />
              {t(`scopes.${s}.${subject.type}`)}
            </label>
          ))}
          {scope === "chosen" ? (
            <ul className="ml-6 flex max-h-56 flex-col gap-1 overflow-y-auto rounded-control border border-rule p-2">
              {sections.map((s) => (
                <li key={s.id}>
                  <label className="flex items-center gap-2 text-ink">
                    <input
                      type="checkbox"
                      checked={chosen.includes(s.id)}
                      onChange={(e) =>
                        setChosen((c) => (e.target.checked ? [...c, s.id] : c.filter((x) => x !== s.id)))
                      }
                    />
                    {s.label}
                  </label>
                </li>
              ))}
            </ul>
          ) : null}
        </fieldset>
      ) : null}

      {!course ? (
        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 font-medium text-ink">{t("format")}</legend>
          {(["json", "zip"] as const).map((f) => (
            <label key={f} className="flex items-start gap-2 text-ink">
              <input
                type="radio"
                className="mt-1"
                name={`${name}-format`}
                checked={format === f}
                onChange={() => setFormat(f)}
              />
              <span>
                {t(`formats.${f}`)}
                <span className="block text-muted">{t(`formatHints.${f}`)}</span>
              </span>
            </label>
          ))}
        </fieldset>
      ) : (
        <p>{t("courseExportHint")}</p>
      )}

      {start.error ? <Alert tone="error">{errorMessage(start.error)}</Alert> : null}
      <div>
        {format === "json" && !course ? (
          <a
            href={
              ready ? documentPackageUrl(subject.type === "post" ? "posts" : "lessons", subject.id, picked) : undefined
            }
            aria-disabled={!ready}
            className={buttonVariants({ size: "sm", className: ready ? "" : "pointer-events-none opacity-50" })}
          >
            <Download aria-hidden />
            {t("downloadJson")}
          </a>
        ) : (
          <Button size="sm" onClick={() => start.mutate()} loading={start.isPending} disabled={!ready}>
            <PackageOpen aria-hidden />
            {t("prepareZip")}
          </Button>
        )}
      </div>
    </div>
  );
}

/** Waits for the worker to build the zip, then offers the download. */
function ExportProgress({ initial }: { initial: Transfer }) {
  const t = useTranslations("content.packages");
  const errorMessage = useErrorMessage();
  const { transfer = initial, stalled } = useTransfer(initial.id, initial);
  const link = useMutation({ mutationFn: () => exportDownloadUrl(initial.id) });

  const ready = transfer.status === "ready";
  const { mutate } = link;
  useEffect(() => {
    if (ready) mutate();
  }, [ready, mutate]);
  const summary = transfer.report && "images" in transfer.report ? transfer.report : null;

  return (
    <div className="flex flex-col gap-4 text-sm" aria-live="polite">
      {transfer.status === "exporting" ? <p className="text-ink">{t("exporting")}</p> : null}
      {stalled ? <Alert tone="info">{t("stalled")}</Alert> : null}
      {transfer.status === "failed" ? <Alert tone="error">{transfer.error ?? t("failed")}</Alert> : null}
      {link.error ? <Alert tone="error">{errorMessage(link.error)}</Alert> : null}
      {ready && summary ? <p>{t("exportReady", { documents: summary.documents, images: summary.images })}</p> : null}
      {link.data ? (
        <div>
          <a href={link.data.url} download={transfer.file_name} className={buttonVariants({ size: "sm" })}>
            <Download aria-hidden />
            {t("downloadZip", { name: transfer.file_name })}
          </a>
        </div>
      ) : null}
    </div>
  );
}
