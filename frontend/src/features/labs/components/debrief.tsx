"use client";

import { Check, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { cn } from "@/lib/utils";
import type { Debrief as DebriefData } from "../types";

/**
 * What a learner reads once a lab is complete (D80): each piece of evidence with the author's
 * explanation, which ones were missed, and which marks were not evidence. The server sends it only
 * for a complete attempt; every string here is shown as text.
 */
export function Debrief({ debrief }: { debrief: DebriefData }) {
  const t = useTranslations("labs.debrief");

  return (
    <section aria-labelledby="debrief-heading" className="panel flex flex-col gap-4 p-5">
      <div className="flex flex-col gap-1">
        <h3 id="debrief-heading" tabIndex={-1} className="scroll-mt-24 text-[length:var(--text-title)] outline-none">
          {t("title")}
        </h3>
        <p className="measured text-sm">{t("intro")}</p>
      </div>

      {debrief.indicators?.length ? (
        <ul className="flex flex-col gap-3">
          {debrief.indicators.map((indicator) => (
            <li key={indicator.id} className="flex flex-col gap-1.5 rounded-card border border-rule bg-paper-2 p-4">
              <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                {indicator.found ? (
                  <Check aria-hidden className="size-4 shrink-0 text-accent" />
                ) : (
                  <X aria-hidden className="size-4 shrink-0 text-bad" />
                )}
                <span>{indicator.title}</span>
                <span
                  className={cn(
                    "rounded-control border px-1.5 py-0.5 text-xs font-medium",
                    indicator.found ? "border-accent text-ink" : "border-bad text-bad",
                  )}
                >
                  {indicator.found ? t("found") : t("missed")}
                  {indicator.required ? "" : ` · ${t("bonus")}`}
                </span>
              </p>
              <p className="measured text-sm">{indicator.explanation}</p>
              <ul className="flex flex-col gap-0.5 text-xs">
                {indicator.lines.map((l) => (
                  <li key={`${l.artifact}-${l.view}-${l.line}`} className="flex flex-col gap-0.5 sm:flex-row sm:gap-2">
                    <span className="shrink-0 text-muted">
                      {t("where", { artifact: l.artifact, view: l.view, line: l.line })}
                    </span>
                    <span className="min-w-0 font-mono break-words whitespace-pre-wrap text-ink">{l.text}</span>
                  </li>
                ))}
              </ul>
            </li>
          ))}
        </ul>
      ) : null}

      {debrief.path ? (
        <div className="flex flex-col gap-3">
          <h4 className="text-sm font-medium text-ink">{t("path")}</h4>
          <ol className="flex flex-col gap-3">
            {debrief.path.map((step, i) => (
              <li key={i} className="flex flex-col gap-1.5 rounded-card border border-rule bg-paper-2 p-4">
                <p className="text-sm font-medium text-ink">
                  <span className="sr-only">{t("step", { number: i + 1 })}: </span>
                  {step.decision}
                </p>
                <p className="measured text-sm">{step.tradeoff}</p>
                {step.alternatives.length ? (
                  <div className="flex flex-col gap-1">
                    <p className="text-xs font-medium text-ink">{t("instead")}</p>
                    <ul className="flex flex-col gap-1.5 text-xs">
                      {step.alternatives.map((a) => (
                        <li key={a.label} className="flex flex-col gap-0.5">
                          <span className="text-ink">
                            {a.label}{" "}
                            <span
                              className={cn(
                                "rounded-control border px-1.5 py-0.5 font-medium",
                                a.keeps_open ? "border-accent text-ink" : "border-bad text-bad",
                              )}
                            >
                              {a.keeps_open ? t("keepsOpen") : t("closesDoor")}
                            </span>
                          </span>
                          <span>{a.tradeoff}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : null}
              </li>
            ))}
          </ol>
        </div>
      ) : null}

      {debrief.final ? (
        <div className="flex flex-col gap-1.5">
          <h4 className="text-sm font-medium text-ink">{t("finalState")}</h4>
          <dl className="grid gap-1 text-sm sm:grid-cols-2">
            {debrief.final.map((f) => (
              <div key={f.label} className="flex gap-2">
                <dt className="text-muted">{f.label}:</dt>
                <dd className="text-ink">{f.value}</dd>
              </div>
            ))}
          </dl>
        </div>
      ) : null}

      {debrief.wrong ? (
        <div className="flex flex-col gap-1.5">
          <h4 className="text-sm font-medium text-ink">{t("wrong")}</h4>
          {debrief.wrong.length ? (
            <ul className="flex flex-col gap-0.5 text-xs">
              {debrief.wrong.map((l) => (
                <li key={`${l.artifact}-${l.view}-${l.line}`} className="flex flex-col gap-0.5 sm:flex-row sm:gap-2">
                  <span className="shrink-0 text-muted">
                    {t("where", { artifact: l.artifact, view: l.view, line: l.line })}
                  </span>
                  <span className="min-w-0 font-mono break-words whitespace-pre-wrap text-ink">{l.text}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm">{t("noneWrong")}</p>
          )}
        </div>
      ) : null}

      {debrief.conclusions.map((c) => (
        <div key={c.checkpoint} className="flex flex-col gap-1">
          <h4 className="text-sm font-medium text-ink">{t("verdict", { title: c.title })}</h4>
          {c.tries ? <p className="text-sm text-ink">{t("tries", { count: c.tries })}</p> : null}
          {c.explanation ? <p className="measured text-sm">{c.explanation}</p> : null}
        </div>
      ))}
    </section>
  );
}
