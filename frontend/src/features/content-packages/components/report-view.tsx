"use client";

import { Check, Copy } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { chatbotErrorText } from "../chatbot-text";
import type { PackageReport, ReportDocument, SectionChange } from "../types";

const CHANGE_STYLE: Record<Exclude<SectionChange["change"], "kept">, string> = {
  added: "border-[color-mix(in_oklab,var(--color-ok)_45%,var(--color-rule))] text-ok",
  replaced: "border-[color-mix(in_oklab,var(--color-attn)_45%,var(--color-rule))] text-attn",
  removed: "border-[color-mix(in_oklab,var(--color-bad)_45%,var(--color-rule))] text-bad",
};

/**
 * The preview of a package (D67): errors by place in the package (with the text to paste back to
 * the chatbot), warnings, and per document the sections it adds, replaces or removes.
 * `linked` makes each document a link to its editor (after it was applied).
 */
export function ReportView({ report, linked = false }: { report: PackageReport; linked?: boolean }) {
  const t = useTranslations("content.packages");
  const listed = Object.values(report.errors).flat().length;
  const errorCount = listed + (report.more_errors ?? 0);

  return (
    <div className="flex flex-col gap-4 text-sm">
      {errorCount > 0 ? (
        <div className="flex flex-col gap-2">
          <Alert tone="error">{t("errors", { count: errorCount })}</Alert>
          <ul className="flex max-h-56 flex-col gap-1.5 overflow-y-auto rounded-control border border-rule bg-paper p-3">
            {Object.entries(report.errors).flatMap(([path, messages]) =>
              messages.map((message, i) => (
                <li key={`${path}-${i}`} className="flex flex-col">
                  <code className="measured text-xs text-muted">{path}</code>
                  <span className="text-ink">{message}</span>
                </li>
              )),
            )}
          </ul>
          {report.more_errors ? <p className="text-muted">{t("moreErrors", { count: report.more_errors })}</p> : null}
          <CopyErrors text={chatbotErrorText(report)} />
        </div>
      ) : null}

      {report.warnings.length > 0 ? (
        <details open={errorCount === 0} className="rounded-control border border-rule px-3 py-2">
          <summary className="cursor-pointer rounded-chip font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
            {t("warnings", { count: report.warnings.length })}
          </summary>
          <ul className="mt-2 flex max-h-48 flex-col gap-1.5 overflow-y-auto">
            {report.warnings.map((w, i) => (
              <li key={i} className="flex flex-col">
                <code className="measured text-xs text-muted">{w.path}</code>
                <span className="text-ink">{w.message}</span>
              </li>
            ))}
          </ul>
        </details>
      ) : null}

      {report.documents.map((doc) => (
        <DocumentChanges key={`${doc.type}:${doc.slug}`} doc={doc} linked={linked} />
      ))}
    </div>
  );
}

function DocumentChanges({ doc, linked }: { doc: ReportDocument; linked: boolean }) {
  const t = useTranslations("content.packages");
  const changed = doc.changes.filter((c) => c.change !== "kept");
  const kept = doc.changes.length - changed.length;
  const href = doc.id ? (doc.type === "lesson" ? `/admin/lessons/${doc.id}` : `/admin/posts/${doc.id}`) : null;
  const name = doc.title || doc.slug;

  return (
    <section className="flex flex-col gap-2 rounded-control border border-rule p-3" aria-label={name}>
      <p className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
        {linked && href ? (
          <Link href={href} className="font-medium text-ink underline underline-offset-4">
            {name}
          </Link>
        ) : (
          <span className="font-medium text-ink">{name}</span>
        )}
        <span className="text-muted">
          {doc.is_new ? t(`newDocument.${doc.type}`) : t(`existingDocument.${doc.type}`)}
          {doc.chapter ? ` · ${doc.chapter.title}` : ""}
        </span>
      </p>
      {changed.length === 0 ? <p>{t("noChanges")}</p> : null}
      <ul className="flex flex-col gap-1">
        {changed.map((c) => (
          <li key={`${c.change}-${c.uuid}`} className="flex flex-wrap items-baseline gap-2">
            <span
              className={cn(
                "rounded-chip border px-1.5 text-xs font-medium",
                CHANGE_STYLE[c.change as keyof typeof CHANGE_STYLE],
              )}
            >
              {t(`changes.${c.change}`)}
            </span>
            <span className="text-ink">{c.heading ?? c.type}</span>
            <code className="measured text-xs text-muted">{c.key}</code>
          </li>
        ))}
      </ul>
      {kept > 0 ? <p className="text-muted">{t("kept", { count: kept })}</p> : null}
    </section>
  );
}

/** Copies the errors as plain text for the chatbot that wrote the package. */
function CopyErrors({ text }: { text: string }) {
  const t = useTranslations("content.packages");
  const [copied, setCopied] = useState(false);
  return (
    <div className="flex items-center gap-3">
      <Button
        variant="secondary"
        size="sm"
        onClick={async () => {
          await navigator.clipboard.writeText(text);
          setCopied(true);
        }}
      >
        {copied ? <Check aria-hidden /> : <Copy aria-hidden />}
        {t("copyErrors")}
      </Button>
      <span className="text-muted" aria-live="polite">
        {copied ? t("copied") : ""}
      </span>
    </div>
  );
}
