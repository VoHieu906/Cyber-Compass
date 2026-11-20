"use client";

import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import type { ReactNode } from "react";
import type { AuditEntry } from "../types";

/** "user.role_changed" -> the message key "user_role_changed" (next-intl reads dots as nesting). */
const messageKey = (action: string) => action.replaceAll(".", "_");

/**
 * Admin actions in plain language, newest first. Used by the overview, the user
 * detail page and the audit log, so an action reads the same everywhere.
 */
export function AuditList({
  entries,
  empty,
  linkSubjects = true,
}: {
  entries: AuditEntry[];
  empty: string;
  linkSubjects?: boolean;
}) {
  if (entries.length === 0) return <p className="text-sm">{empty}</p>;

  return (
    <ol className="flex flex-col">
      {entries.map((entry) => (
        <li key={entry.id} className="border-b border-rule py-3 first:pt-0 last:border-b-0 last:pb-0">
          <AuditLine entry={entry} linkSubjects={linkSubjects} />
        </li>
      ))}
    </ol>
  );
}

/** Where an audit subject can be opened; deleted lessons and media have no page. */
function subjectLink(type: string, id: number): string | null {
  if (type === "user") return `/admin/users/${id}`;
  if (type === "lesson") return `/admin/lessons/${id}`;
  if (type === "course") return `/admin/courses?course=${id}`;
  return null;
}

function AuditLine({ entry, linkSubjects }: { entry: AuditEntry; linkSubjects: boolean }) {
  const t = useTranslations("admin.audit");
  const tRoles = useTranslations("admin.roles");
  const format = useFormatter();

  const actor = entry.actor?.name ?? t("system");
  const subjectName = entry.subject.label ?? `#${entry.subject.id ?? "?"}`;
  const subjectHref = linkSubjects && entry.subject.id ? subjectLink(entry.subject.type, entry.subject.id) : null;
  const subjectTag = (chunks: ReactNode) =>
    subjectHref ? (
      <Link href={subjectHref} className="font-medium text-ink underline-offset-4 hover:underline">
        {chunks}
      </Link>
    ) : (
      <span className="font-medium text-ink">{chunks}</span>
    );

  const role = entry.changes.role as [string, string] | undefined;
  const roleName = (r: string | undefined) => (r === "admin" || r === "learner" ? tRoles(r) : (r ?? ""));
  const key = messageKey(entry.action);
  const reason = (entry.changes.reason ?? entry.changes.previous_reason) as string | undefined;

  return (
    <div className="flex flex-col gap-1 text-sm sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
      <div className="flex min-w-0 flex-col gap-1">
        <p className="text-ink-2">
          {t.has(`actions.${key}`)
            ? t.rich(`actions.${key}`, {
                actor,
                subject: subjectName,
                from: roleName(role?.[0]),
                to: roleName(role?.[1]),
                version: Number(entry.changes.version ?? 0),
                b: (chunks) => <span className="font-medium text-ink">{chunks}</span>,
                link: subjectTag,
              })
            : `${actor}: ${entry.action}`}
        </p>
        {reason ? (
          <p className="text-muted">{t(entry.changes.reason ? "reason" : "previousReason", { reason })}</p>
        ) : null}
        {typeof entry.changes.note === "string" ? (
          <p className="text-muted">{t("note", { note: entry.changes.note })}</p>
        ) : null}
      </div>
      {entry.created_at ? (
        <time
          dateTime={entry.created_at}
          title={format.dateTime(new Date(entry.created_at), { dateStyle: "medium", timeStyle: "short" })}
          className="measured shrink-0 text-xs text-muted"
        >
          {format.dateTime(new Date(entry.created_at), { dateStyle: "short", timeStyle: "short" })}
        </time>
      ) : null}
    </div>
  );
}
