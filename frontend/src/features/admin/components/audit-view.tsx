"use client";

import { useTranslations } from "next-intl";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { Pagination } from "@/components/ui/pagination";
import { useAuditLog } from "../hooks";
import { auditQueryToSearch, parseAuditQuery } from "../query-state";
import { SUBJECT_TYPES, type AuditQuery } from "../types";
import { AuditList } from "./audit-list";

export function AuditView() {
  const t = useTranslations("admin.audit");
  const router = useRouter();
  const pathname = usePathname();
  const query = parseAuditQuery(useSearchParams());
  const log = useAuditLog(query);

  function update(patch: Partial<AuditQuery>) {
    const next = { ...query, ...patch, page: patch.page ?? 1 };
    router.replace(`${pathname}${auditQueryToSearch(next)}`, { scroll: false });
  }

  const page = log.data;
  const filtered = Boolean(query.actor || query.subjectType || query.subjectId);

  return (
    <div className="flex flex-col gap-6">
      <p className="max-w-[44rem] text-sm">{t("intro")}</p>
      <div className="flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1.5 text-sm font-medium text-ink max-sm:w-full">
          {t("subjectType")}
          <select
            className={controlClasses(false, "w-full sm:w-auto")}
            value={query.subjectType}
            onChange={(e) => update({ subjectType: e.target.value as AuditQuery["subjectType"], subjectId: null })}
          >
            <option value="">{t("anySubject")}</option>
            {SUBJECT_TYPES.map((s) => (
              <option key={s} value={s}>
                {t(`subjects.${s}`)}
              </option>
            ))}
          </select>
        </label>
        {filtered ? (
          <Button variant="ghost" size="sm" onClick={() => router.replace(pathname, { scroll: false })}>
            {t("clearFilters")}
          </Button>
        ) : null}
      </div>
      {query.actor || query.subjectId ? (
        <p className="text-sm text-ink">
          {query.actor
            ? t("filteredByActor", { id: query.actor })
            : t("filteredBySubject", { id: query.subjectId ?? 0 })}
        </p>
      ) : null}

      {log.isError ? (
        <div className="flex flex-col items-start gap-3">
          <Alert tone="error">{t("loadError")}</Alert>
          <Button variant="secondary" onClick={() => log.refetch()}>
            {t("retry")}
          </Button>
        </div>
      ) : !page ? (
        <div aria-hidden className="h-80 rounded-card bg-paper-3" />
      ) : (
        <div className="panel p-5" aria-busy={log.isFetching} style={{ opacity: log.isPlaceholderData ? 0.6 : 1 }}>
          <AuditList entries={page.data} empty={filtered ? t("emptyFiltered") : t("empty")} />
        </div>
      )}

      {page ? (
        <Pagination
          page={page.meta.current_page}
          totalPages={page.meta.last_page}
          onChange={(p) => {
            update({ page: p });
            window.scrollTo({ top: 0 });
          }}
          labels={{
            nav: t("pagination"),
            previous: t("previous"),
            next: t("next"),
            page: t("page", { page: page.meta.current_page, total: page.meta.last_page }),
          }}
        />
      ) : null}
    </div>
  );
}
