"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { FileUp, RotateCcw, Upload } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useId, useRef, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { controlClasses } from "@/components/ui/input";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { ApiError } from "@/lib/api/client";
import { applyTransfer, checkPackage, validateAgain } from "../api";
import { useTransfer } from "../hooks";
import type { PackageReport, Transfer } from "../types";
import { uploadPackage } from "../upload";
import { ReportView } from "./report-view";

type Phase =
  | { step: "choose" }
  | { step: "uploading"; sent: number; total: number }
  | { step: "transfer"; initial: Transfer }
  | { step: "checked"; report: PackageReport };

type Props = {
  open: boolean;
  onClose: () => void;
  /** Called once when a package has been written into drafts (the editor reloads, lists refresh). */
  onApplied?: () => void;
  /** Why importing is not possible right now (unsaved edits in the editor); shown instead of the actions. */
  blockedReason?: string | null;
};

/**
 * Imports a content package (F6, D67): upload (or paste to check only), the worker's preview,
 * then apply into drafts. Nothing is published here. The body is unmounted when the dialog
 * closes, which stops polling and cancels an upload in progress.
 */
export function ImportDialog({ open, onClose, onApplied, blockedReason }: Props) {
  const t = useTranslations("content.packages");
  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={t("importTitle")}
      description={t("importIntro")}
      closeLabel={t("close")}
      className="w-[min(44rem,calc(100vw-2*var(--gutter)))]"
    >
      {open ? <ImportBody onApplied={onApplied} blockedReason={blockedReason} /> : null}
    </Dialog>
  );
}

/**
 * One status line for screen readers that lives as long as the dialog: a live region must exist
 * before its text changes, and the steps below come and go.
 */
function ImportBody(props: Pick<Props, "onApplied" | "blockedReason">) {
  const [status, setStatus] = useState("");
  return (
    <>
      <p role="status" aria-live="polite" className="sr-only">
        {status}
      </p>
      <ImportSteps {...props} announce={setStatus} />
    </>
  );
}

function ImportSteps({
  onApplied,
  blockedReason,
  announce,
}: Pick<Props, "onApplied" | "blockedReason"> & { announce: (text: string) => void }) {
  const t = useTranslations("content.packages");
  const errorMessage = useErrorMessage();
  const [phase, setPhase] = useState<Phase>({ step: "choose" });
  const [file, setFile] = useState<File | null>(null);
  const [pasted, setPasted] = useState("");
  const [failure, setFailure] = useState<string | null>(null);
  const abort = useRef<AbortController | null>(null);
  const fileId = useId();
  const pasteId = useId();

  useEffect(() => () => abort.current?.abort(), []);

  const upload = async () => {
    if (!file) return;
    setFailure(null);
    abort.current = new AbortController();
    setPhase({ step: "uploading", sent: 0, total: file.size });
    announce(t("uploading", { name: file.name }));
    try {
      const transfer = await uploadPackage(
        file,
        (sent) => setPhase({ step: "uploading", sent, total: file.size }),
        abort.current.signal,
      );
      setPhase({ step: "transfer", initial: transfer });
    } catch (error) {
      if (error instanceof DOMException && error.name === "AbortError") return;
      setFailure(errorMessage(error));
      setPhase({ step: "choose" });
    }
  };

  const check = useMutation({
    mutationFn: () => checkPackage(pasted),
    onSuccess: (report) => {
      setPhase({ step: "checked", report });
      announce(report.valid ? t("checkedValid") : t("checkedInvalid"));
    },
    onError: (error) =>
      setFailure(error instanceof ApiError && error.fieldErrors.json ? error.fieldErrors.json[0] : errorMessage(error)),
  });

  if (phase.step === "uploading") {
    const percent = Math.floor((phase.sent / Math.max(1, phase.total)) * 100);
    return (
      <div className="flex flex-col gap-2 text-sm">
        <p className="text-ink">{t("uploading", { name: file?.name ?? "" })}</p>
        <div
          role="progressbar"
          aria-label={t("uploadProgress")}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={percent}
          className="h-2 overflow-hidden rounded-full bg-paper-3"
        >
          <div
            className="h-full origin-left bg-accent transition-transform duration-[var(--dur-micro)]"
            style={{ transform: `scaleX(${percent / 100})` }}
          />
        </div>
        <p className="measured text-xs text-muted">
          {t("uploadedOf", { sent: mb(phase.sent), total: mb(phase.total) })}
        </p>
      </div>
    );
  }

  if (phase.step === "transfer")
    return (
      <TransferSteps
        initial={phase.initial}
        onApplied={onApplied}
        onRestart={() => setPhase({ step: "choose" })}
        announce={announce}
      />
    );

  if (phase.step === "checked") {
    return (
      <div className="flex flex-col gap-4">
        <Alert tone="info">{phase.report.valid ? t("checkedValid") : t("checkedInvalid")}</Alert>
        <ReportView report={phase.report} />
        <div>
          <Button variant="secondary" size="sm" onClick={() => setPhase({ step: "choose" })}>
            <RotateCcw aria-hidden />
            {t("back")}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-5 text-sm">
      {blockedReason ? <Alert tone="info">{blockedReason}</Alert> : null}
      {failure ? <Alert tone="error">{failure}</Alert> : null}
      <div className="flex flex-col gap-2">
        <label htmlFor={fileId} className="font-medium text-ink">
          {t("file")}
        </label>
        <input
          id={fileId}
          type="file"
          accept=".json,.zip,application/json,application/zip"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          className="text-sm file:mr-3 file:rounded-control file:border file:border-rule-2 file:bg-paper-2 file:px-3 file:py-1.5 file:text-ink"
        />
        <p className="text-muted">{t("fileHint")}</p>
        <div>
          <Button size="sm" onClick={() => void upload()} disabled={!file || !!blockedReason}>
            <Upload aria-hidden />
            {t("uploadAndCheck")}
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-2 border-t border-rule pt-4">
        <label htmlFor={pasteId} className="font-medium text-ink">
          {t("paste")}
        </label>
        <textarea
          id={pasteId}
          value={pasted}
          onChange={(e) => setPasted(e.target.value)}
          rows={5}
          spellCheck={false}
          className={controlClasses(false, "measured text-xs")}
        />
        <p className="text-muted">{t("pasteHint")}</p>
        <div>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => check.mutate()}
            loading={check.isPending}
            disabled={pasted.trim() === ""}
          >
            <FileUp aria-hidden />
            {t("checkOnly")}
          </Button>
        </div>
      </div>
    </div>
  );
}

/** Follows the uploaded package through the worker: preview, apply, done or failed. */
function TransferSteps({
  initial,
  onApplied,
  onRestart,
  announce,
}: {
  initial: Transfer;
  onApplied?: () => void;
  onRestart: () => void;
  announce: (text: string) => void;
}) {
  const t = useTranslations("content.packages");
  const errorMessage = useErrorMessage();
  const queryClient = useQueryClient();
  const { transfer = initial, stalled } = useTransfer(initial.id, initial);
  const key = ["admin", "content", "transfer", initial.id];
  const adopt = (next: Transfer) => queryClient.setQueryData(key, next);
  const apply = useMutation({ mutationFn: () => applyTransfer(initial.id), onSuccess: adopt });
  const again = useMutation({ mutationFn: () => validateAgain(initial.id), onSuccess: adopt });

  const reported = useRef(false);
  useEffect(() => {
    if (transfer.status === "applied" && !reported.current) {
      reported.current = true;
      onApplied?.();
    }
  }, [onApplied, transfer.status]);

  const report = transfer.report && "valid" in transfer.report ? transfer.report : null;
  const actionError = apply.error ?? again.error;

  // What the status line says for screen readers; failures speak through their own alert.
  const valid = report?.valid ?? false;
  const spoken = stalled
    ? t("stalled")
    : {
        uploading: "",
        validating: t("validating"),
        ready: valid ? t("readyToApply") : t("checkedInvalid"),
        applying: t("applying"),
        applied: t("applied"),
        exporting: "",
        failed: "",
      }[transfer.status];
  useEffect(() => announce(spoken), [announce, spoken]);

  return (
    <div className="flex flex-col gap-4 text-sm">
      {transfer.status === "validating" ? <p className="text-ink">{t("validating")}</p> : null}
      {transfer.status === "applying" ? <p className="text-ink">{t("applying")}</p> : null}
      {stalled ? <Alert tone="info">{t("stalled")}</Alert> : null}
      {transfer.status === "applied" ? <Alert tone="success">{t("applied")}</Alert> : null}
      {transfer.status === "failed" ? <Alert tone="error">{transfer.error ?? t("failed")}</Alert> : null}
      {actionError ? <Alert tone="error">{errorMessage(actionError)}</Alert> : null}

      {report && (transfer.status === "ready" || transfer.status === "applied") ? (
        <ReportView report={report} linked={transfer.status === "applied"} />
      ) : null}

      <div className="flex flex-wrap gap-2">
        {transfer.status === "ready" && report?.valid ? (
          <Button size="sm" onClick={() => apply.mutate()} loading={apply.isPending}>
            {t("apply")}
          </Button>
        ) : null}
        {transfer.status === "failed" ? (
          <Button variant="secondary" size="sm" onClick={() => again.mutate()} loading={again.isPending}>
            <RotateCcw aria-hidden />
            {t("validateAgain")}
          </Button>
        ) : null}
        {transfer.status === "ready" || transfer.status === "failed" || transfer.status === "applied" ? (
          <Button variant="ghost" size="sm" onClick={onRestart}>
            {t("another")}
          </Button>
        ) : null}
      </div>
    </div>
  );
}

const mb = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(1)} MB`;
