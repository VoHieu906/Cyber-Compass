"use client";

import { ArrowDown, ArrowUp, FilePlus, TriangleAlert } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { controlClasses } from "@/components/ui/input";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { LessonStatusBadge, UnpublishedChip } from "@/features/content-editor/components/labels";
import { slugify } from "@/features/content-editor/query-state";
import { ApiError } from "@/lib/api/client";
import { cn } from "@/lib/utils";
import { useAdminLabs, useArchiveLab, useCreateLab, useOrderLabs } from "../hooks";
import type { DomainInfo, EngineInfo, LabRow } from "../types";

/** Admin → Labs (F7): every lab with its status, who owns it (a content file or the database), and the order learners see. */
export function LabsAdminView() {
  const t = useTranslations("labAdmin");
  const labs = useAdminLabs();
  const order = useOrderLabs();
  const archive = useArchiveLab();
  const [creating, setCreating] = useState(false);

  const rows = labs.data?.data ?? [];
  const move = (index: number, by: -1 | 1) => {
    const ids = rows.map((r) => r.id);
    [ids[index], ids[index + by]] = [ids[index + by], ids[index]];
    order.mutate(ids);
  };

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <h1 className="text-[length:var(--text-2xl)]">{t("title")}</h1>
        <Button onClick={() => setCreating(true)} disabled={!labs.data}>
          <FilePlus aria-hidden />
          {t("new")}
        </Button>
      </div>
      <p className="max-w-[48rem] text-sm">{t("intro")}</p>

      {labs.isError ? (
        <div className="flex flex-col items-start gap-3">
          <Alert tone="error">{t("loadError")}</Alert>
          <Button variant="secondary" onClick={() => labs.refetch()}>
            {t("retry")}
          </Button>
        </div>
      ) : !labs.data ? (
        <div aria-hidden className="h-64 rounded-card bg-paper-3" />
      ) : (
        <div className="panel overflow-hidden" aria-busy={order.isPending}>
          <table className="w-full border-collapse text-left text-sm">
            <caption className="sr-only">{t("title")}</caption>
            <thead className="max-md:sr-only">
              <tr className="border-b border-rule text-xs text-muted">
                <th scope="col" className="px-5 py-3 font-medium">{t("columns.lab")}</th>
                <th scope="col" className="px-3 py-3 font-medium">{t("columns.status")}</th>
                <th scope="col" className="px-3 py-3 font-medium">{t("columns.owner")}</th>
                <th scope="col" className="px-3 py-3 text-right font-medium">{t("columns.learners")}</th>
                <th scope="col" className="px-5 py-3 text-right font-medium">{t("columns.actions")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((lab, i) => (
                <LabTableRow
                  key={lab.id}
                  lab={lab}
                  first={i === 0}
                  last={i === rows.length - 1}
                  busy={order.isPending || archive.isPending}
                  onMove={(by) => move(i, by)}
                  onArchive={() => archive.mutate({ id: lab.id, archived: !lab.is_archived })}
                />
              ))}
            </tbody>
          </table>
        </div>
      )}
      {order.isError ? <Alert tone="error">{t("orderError")}</Alert> : null}

      {labs.data ? <NewLabDialog open={creating} onClose={() => setCreating(false)} domains={labs.data.meta.domains} engines={labs.data.meta.engines} /> : null}
    </div>
  );
}

function LabTableRow({
  lab,
  first,
  last,
  busy,
  onMove,
  onArchive,
}: {
  lab: LabRow;
  first: boolean;
  last: boolean;
  busy: boolean;
  onMove: (by: -1 | 1) => void;
  onArchive: () => void;
}) {
  const t = useTranslations("labAdmin");
  const te = useTranslations("labs.engines");
  const status = lab.is_archived ? "archived" : lab.status;
  return (
    <tr
      className={cn(
        "border-b border-rule last:border-b-0 hover:bg-paper-3 max-md:flex max-md:flex-col max-md:gap-2 max-md:p-4",
        lab.is_archived && "text-muted",
      )}
    >
      <td className="px-5 py-3 max-md:p-0">
        <Link href={`/admin/labs/${lab.id}`} className="font-medium text-ink underline-offset-4 hover:underline">
          {lab.title}
        </Link>
        <p className="text-xs text-muted">
          <code>{lab.slug}</code> · {te(lab.engine)} · {lab.domain.name}
        </p>
      </td>
      <td className="px-3 py-3 max-md:p-0">
        <span className="flex flex-wrap items-center gap-2">
          <LessonStatusBadge status={status} />
          {lab.has_draft && lab.status === "published" ? <UnpublishedChip /> : null}
        </span>
      </td>
      <td className="px-3 py-3 max-md:p-0">
        <p className="text-ink">{lab.owned ? t("ownerDatabase") : t("ownerFile")}</p>
        {lab.shadowed ? (
          <p className="mt-1 flex max-w-[22rem] items-start gap-1.5 text-xs text-ink">
            <TriangleAlert aria-hidden className="mt-0.5 size-3.5 shrink-0 text-attn" />
            {t("shadowed", { slug: lab.slug })}
          </p>
        ) : !lab.owned ? (
          <p className="max-w-[22rem] text-xs text-muted">{t("ownerFileHint")}</p>
        ) : null}
      </td>
      <td className="px-3 py-3 text-right tabular-nums max-md:p-0 max-md:text-left">{t("learnersCount", { started: lab.started, completed: lab.completed })}</td>
      <td className="px-5 py-3 max-md:p-0">
        <div className="flex justify-end gap-1 max-md:justify-start">
          <Button variant="ghost" size="sm" disabled={busy || first} onClick={() => onMove(-1)} aria-label={t("moveUp", { title: lab.title })}>
            <ArrowUp aria-hidden />
          </Button>
          <Button variant="ghost" size="sm" disabled={busy || last} onClick={() => onMove(1)} aria-label={t("moveDown", { title: lab.title })}>
            <ArrowDown aria-hidden />
          </Button>
          <Button variant="ghost" size="sm" disabled={busy} onClick={onArchive}>
            {lab.is_archived ? t("unarchive") : t("archive")}
          </Button>
        </div>
      </td>
    </tr>
  );
}

function NewLabDialog({ open, onClose, domains, engines }: { open: boolean; onClose: () => void; domains: DomainInfo[]; engines: EngineInfo[] }) {
  const t = useTranslations("labAdmin.create");
  const tc = useTranslations("common");
  const te = useTranslations("labs.engines");
  const router = useRouter();
  const create = useCreateLab();
  const errorMessage = useErrorMessage();
  const ids = { title: useId(), slug: useId(), engine: useId(), domain: useId() };
  const [title, setTitle] = useState("");
  const [slug, setSlug] = useState("");
  const [slugTouched, setSlugTouched] = useState(false);
  const [engine, setEngine] = useState(engines[0]?.key ?? "linux-shell");
  const [domain, setDomain] = useState(domains[0]?.slug ?? "");
  const slugErrors: Record<string, string[]> = create.error instanceof ApiError ? create.error.fieldErrors : {};

  return (
    <Dialog open={open} onClose={onClose} title={t("title")} description={t("body")} closeLabel={tc("close")}>
      <form
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault();
          create.mutate({ slug, engine, title: title.trim(), domain }, { onSuccess: (lab) => router.push(`/admin/labs/${lab.id}`) });
        }}
      >
        <label htmlFor={ids.title} className="flex flex-col gap-1.5 text-sm font-medium text-ink">
          {t("labTitle")}
          <input
            id={ids.title}
            value={title}
            required
            maxLength={200}
            onChange={(e) => {
              setTitle(e.target.value);
              if (!slugTouched) setSlug(slugify(e.target.value));
            }}
            className={controlClasses(Boolean(slugErrors.title))}
          />
        </label>
        <label htmlFor={ids.slug} className="flex flex-col gap-1.5 text-sm font-medium text-ink">
          {t("slug")}
          <input
            id={ids.slug}
            value={slug}
            required
            maxLength={120}
            spellCheck={false}
            onChange={(e) => {
              setSlugTouched(true);
              setSlug(e.target.value);
            }}
            aria-invalid={slugErrors.slug ? true : undefined}
            className={controlClasses(Boolean(slugErrors.slug))}
          />
          <span className="text-xs font-normal text-muted">{slugErrors.slug?.join(" ") ?? t("slugHint")}</span>
        </label>
        <div className="grid gap-4 sm:grid-cols-2">
          <label htmlFor={ids.engine} className="flex flex-col gap-1.5 text-sm font-medium text-ink">
            {t("engine")}
            <select id={ids.engine} value={engine} onChange={(e) => setEngine(e.target.value as EngineInfo["key"])} className={controlClasses(false)}>
              {engines.map((e) => (
                <option key={e.key} value={e.key}>
                  {te(e.key)}
                </option>
              ))}
            </select>
          </label>
          <label htmlFor={ids.domain} className="flex flex-col gap-1.5 text-sm font-medium text-ink">
            {t("domain")}
            <select id={ids.domain} value={domain} onChange={(e) => setDomain(e.target.value)} className={controlClasses(false)}>
              {domains.map((d) => (
                <option key={d.slug} value={d.slug}>
                  {d.name}
                </option>
              ))}
            </select>
          </label>
        </div>
        {create.isError && !slugErrors.slug && !slugErrors.title ? <Alert tone="error">{errorMessage(create.error)}</Alert> : null}
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            {tc("cancel")}
          </Button>
          <Button type="submit" loading={create.isPending} disabled={!title.trim() || !slug}>
            {t("submit")}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
