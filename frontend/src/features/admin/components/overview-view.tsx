"use client";

import { ArrowRight } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { useAdminOverview } from "../hooks";
import { AuditList } from "./audit-list";

/** Counts are instruments: mono figures, labels underneath, one hairline-divided strip per group. */
function Readings({
  items,
  columns,
}: {
  items: { label: string; value: number; href?: string; tone?: "bad" }[];
  columns: string;
}) {
  return (
    <dl className={`panels ${columns}`}>
      {items.map(({ label, value, href, tone }) => {
        const figure = (
          <dd
            className={`measured text-[length:var(--text-2xl)] leading-none ${tone === "bad" && value > 0 ? "text-bad" : "text-ink"}`}
          >
            {value}
          </dd>
        );
        return (
          <div key={label} className="flex flex-col-reverse gap-2 p-5">
            <dt className="text-sm">
              {href ? (
                <Link href={href} className="underline-offset-4 hover:text-ink hover:underline">
                  {label}
                </Link>
              ) : (
                label
              )}
            </dt>
            {figure}
          </div>
        );
      })}
    </dl>
  );
}

export function OverviewView() {
  const t = useTranslations("admin.overview");
  const tAudit = useTranslations("admin.audit");
  const overview = useAdminOverview();

  if (overview.isError) {
    return (
      <div className="flex flex-col items-start gap-3">
        <Alert tone="error">{t("loadError")}</Alert>
        <Button variant="secondary" onClick={() => overview.refetch()}>
          {t("retry")}
        </Button>
      </div>
    );
  }

  const data = overview.data;
  if (!data) return <div aria-hidden className="h-64 rounded-card bg-paper-3" />;

  return (
    <div className="flex flex-col gap-10">
      <section aria-labelledby="overview-accounts" className="flex flex-col gap-4">
        <h2 id="overview-accounts" className="text-[length:var(--text-lg)]">
          {t("accounts")}
        </h2>
        <Readings
          columns="grid-cols-2 sm:grid-cols-3 lg:grid-cols-5"
          items={[
            { label: t("users.total"), value: data.users.total, href: "/admin/users" },
            { label: t("users.admins"), value: data.users.admins, href: "/admin/users?role=admin" },
            {
              label: t("users.suspended"),
              value: data.users.suspended,
              href: "/admin/users?status=suspended",
              tone: "bad",
            },
            { label: t("users.new"), value: data.users.new_last_7_days },
            { label: t("users.active"), value: data.users.active_last_7_days },
          ]}
        />
      </section>

      <section aria-labelledby="overview-lessons" className="flex flex-col gap-4">
        <h2 id="overview-lessons" className="text-[length:var(--text-lg)]">
          {t("lessons")}
        </h2>
        <Readings
          columns="grid-cols-3"
          items={[
            { label: t("lessonStatus.published"), value: data.lessons.published },
            { label: t("lessonStatus.draft"), value: data.lessons.draft },
            { label: t("lessonStatus.archived"), value: data.lessons.archived },
          ]}
        />
        <p className="text-sm">{t("lessonsNote")}</p>
      </section>

      <section aria-labelledby="overview-activity" className="flex flex-col gap-4">
        <div className="flex items-baseline justify-between gap-4">
          <h2 id="overview-activity" className="text-[length:var(--text-lg)]">
            {t("activity")}
          </h2>
          <Link
            href="/admin/audit"
            className="inline-flex items-center gap-1.5 text-sm font-medium text-ink underline-offset-4 hover:underline"
          >
            {t("allActivity")}
            <ArrowRight aria-hidden className="size-4" />
          </Link>
        </div>
        <div className="panel p-5">
          <AuditList entries={data.recent_activity} empty={tAudit("empty")} />
        </div>
      </section>
    </div>
  );
}
