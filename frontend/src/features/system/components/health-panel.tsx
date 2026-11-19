"use client";

import { CircleAlert, CircleCheck, CircleDashed } from "lucide-react";
import { useTranslations } from "next-intl";
import type { ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { useHealth } from "../use-health";

type Tone = "ok" | "bad" | "pending";

function Status({ tone, children }: { tone: Tone; children: ReactNode }) {
  const Icon = tone === "ok" ? CircleCheck : tone === "bad" ? CircleAlert : CircleDashed;
  const colour = tone === "ok" ? "text-ok" : tone === "bad" ? "text-bad" : "text-muted";
  return (
    <span className="inline-flex items-center gap-1.5 text-ink">
      <Icon aria-hidden className={`size-4 ${colour}`} />
      {children}
    </span>
  );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 px-5 py-3.5">
      <dt className="text-sm font-medium">{label}</dt>
      <dd className="text-sm">{children}</dd>
    </div>
  );
}

export function HealthPanel({ title }: { title: string }) {
  const t = useTranslations("health");
  const { data, isPending, isError, isFetching, refetch } = useHealth();

  const apiTone: Tone = isPending ? "pending" : isError ? "bad" : "ok";
  const apiLabel = isPending ? t("checking") : isError ? t("down") : t("ok");

  return (
    <section aria-labelledby="health-title" className="panels">
      <div className="flex items-center justify-between gap-4 px-5 py-4">
        <h2 id="health-title" className="text-[length:var(--text-title)]">
          {title}
        </h2>
        <Button variant="secondary" size="sm" onClick={() => refetch()} loading={isFetching && !isPending}>
          {t("retry")}
        </Button>
      </div>
      <dl className="grid divide-y divide-rule">
        <Row label={t("api")}>
          <Status tone={apiTone}>{apiLabel}</Status>
        </Row>
        <Row label={t("database")}>
          {data ? (
            <Status tone={data.database === "ok" ? "ok" : "bad"}>{data.database === "ok" ? t("ok") : t("down")}</Status>
          ) : (
            <span className="text-muted">{t("unknown")}</span>
          )}
        </Row>
        <Row label={t("latency")}>
          <span className="measured text-ink">{data ? `${data.latencyMs} ms` : "–"}</span>
        </Row>
        <Row label={t("version")}>
          <span className="measured text-ink">{data?.version ?? "–"}</span>
        </Row>
      </dl>
      {isError ? (
        <p role="status" className="bg-paper-3 px-5 py-3.5 text-sm">
          {t("downHint")}
        </p>
      ) : null}
    </section>
  );
}
