"use client";

import { ArrowRight } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { Checkpoint, IncidentState, LabAction, LabActionResult } from "../types";

/**
 * An incident as the learner plays it (D80): the technical state, a timeline of decisions with what
 * each did and the attacker's next move, and the choices that are open now. Choices are real
 * buttons, the timeline is an ordered list, and what a choice changed is announced in a live region.
 * When the server says a task can no longer be completed, it is said here plainly, with a pointer to
 * starting over; the page never learns which choice would have helped.
 */
export function IncidentConsole({
  state,
  checkpoints,
  busy,
  onAction,
}: {
  state: IncidentState;
  checkpoints: Checkpoint[];
  busy: boolean;
  onAction: (action: LabAction) => Promise<LabActionResult | null>;
}) {
  const t = useTranslations("labs.incident");
  const [announcement, setAnnouncement] = useState("");

  async function choose(id: string) {
    const result = await onAction({ type: "choose", choice: id });
    if (!result) return;
    const { consequence, attacker, changes = [] } = result.output;
    setAnnouncement(
      [
        consequence,
        changes.length
          ? t("changed", { changes: changes.map((c) => `${c.label}: ${c.from} → ${c.to}`).join("; ") })
          : "",
        attacker ? t("attackerNext", { text: attacker }) : "",
      ]
        .filter(Boolean)
        .join(" "),
    );
  }

  const titles = (state.dead_end?.unreachable ?? [])
    .map((id) => checkpoints.find((c) => c.id === id)?.title)
    .filter(Boolean)
    .join(" ");

  return (
    <div className="flex flex-col gap-4">
      <section aria-labelledby="incident-state" className="panel flex flex-col gap-3 p-4">
        <h3 id="incident-state" className="text-[length:var(--text-title)]">
          {t("state")}
        </h3>
        <dl className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
          {state.vars.map((v) => (
            <div key={v.id} className="flex flex-col gap-0.5 rounded-control border border-rule bg-paper-2 px-3 py-2">
              <dt className="text-xs text-muted">{v.label}</dt>
              <dd className="text-sm font-medium text-ink">{v.value_label}</dd>
            </div>
          ))}
        </dl>
      </section>

      <section aria-labelledby="incident-timeline" className="panel flex flex-col gap-3 p-4">
        <h3 id="incident-timeline" className="text-[length:var(--text-title)]">
          {t("timeline")}
        </h3>
        {state.timeline.length ? (
          <ol className="flex flex-col gap-3">
            {state.timeline.map((entry, i) => (
              <li key={i} className="flex flex-col gap-1 rounded-card border border-rule bg-paper-2 p-3 text-sm">
                <p className="font-medium text-ink">
                  <span className="sr-only">{t("step", { number: i + 1 })}: </span>
                  {entry.decision}
                </p>
                <p>{entry.consequence}</p>
                {entry.changes.length ? (
                  <ul className="flex flex-col gap-0.5 text-xs text-ink">
                    {entry.changes.map((c) => (
                      <li key={c.label}>
                        {c.label}: {c.from} <ArrowRight aria-label={t("to")} className="inline size-3" />{" "}
                        <strong>{c.to}</strong>
                      </li>
                    ))}
                  </ul>
                ) : null}
                <p className="text-xs">
                  <span className="font-medium text-ink">{t("attacker")}</span> {entry.attacker}
                </p>
              </li>
            ))}
          </ol>
        ) : (
          <p className="text-sm">{t("noDecisions")}</p>
        )}
      </section>

      <section aria-labelledby="incident-now" className="panel flex flex-col gap-3 p-4">
        <h3 id="incident-now" className="text-[length:var(--text-title)]">
          {state.ended ? t("ended") : t("now")}
        </h3>
        <p className="measured text-sm text-ink">{state.situation}</p>

        {state.dead_end ? (
          <Alert tone="error">
            <span className="flex flex-col gap-1">
              <span>{titles ? t("deadEnd", { tasks: titles }) : t("deadEndTogether")}</span>
              <span>{t("deadEndReset")}</span>
            </span>
          </Alert>
        ) : null}

        {state.choices.length ? (
          <ul aria-label={t("choices")} className="flex flex-col gap-2">
            {state.choices.map((c) => (
              <li key={c.id} className="flex flex-wrap items-center gap-3">
                <Button
                  variant="secondary"
                  className={cn(
                    "h-auto min-h-9 max-w-full justify-start py-2 text-left whitespace-normal",
                    !c.available && "opacity-60",
                  )}
                  disabled={busy || !c.available}
                  onClick={() => void choose(c.id)}
                >
                  {c.label}
                </Button>
                {!c.available ? <span className="text-xs text-muted">{t("unavailable")}</span> : null}
              </li>
            ))}
          </ul>
        ) : null}
      </section>

      <p role="status" aria-live="polite" className="sr-only">
        {announcement}
      </p>
    </div>
  );
}
