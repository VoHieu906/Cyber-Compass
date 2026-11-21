"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { cn } from "@/lib/utils";
import { renderInline } from "../components/prose";
import type {
  Bitfield as BitfieldData,
  Comparison as ComparisonData,
  StepThrough as StepThroughData,
  Timeline as TimelineData,
  Tone,
} from "./schemas";
import { Stepper } from "./stepper";

/** Fill and border per tone, shared by bit fields and step-through parts. */
export const toneClass: Record<Tone, string> = {
  accent: "border-accent bg-accent-soft text-ink",
  attn: "border-attn bg-attn-soft text-ink",
  muted: "border-rule bg-paper-3 text-muted",
  plain: "border-rule-2 bg-paper text-ink",
};

/** A real table on wide screens; one card per compared thing on narrow ones. */
export function Comparison({ data }: { data: ComparisonData }) {
  return (
    <>
      <div className="hidden overflow-hidden rounded-control border border-rule @xl:block">
        <table className="w-full border-collapse text-left text-sm">
          <thead className="bg-paper-3">
            <tr>
              <td className="w-[22%] border-b border-rule p-3" />
              {data.columns.map((c, i) => (
                <th key={i} scope="col" className="border-b border-l border-rule p-3 font-medium text-ink">
                  {c}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.rows.map((row, r) => (
              <tr key={r} className="align-top [&:not(:last-child)>*]:border-b [&>*]:border-rule">
                <th scope="row" className="p-3 font-medium text-muted">
                  {row.label}
                </th>
                {row.values.map((v, c) => (
                  <td key={c} className="border-l border-rule p-3 text-ink">
                    {renderInline(v)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <ul className="flex flex-col gap-3 @xl:hidden">
        {data.columns.map((c, ci) => (
          <li key={ci} className="rounded-control border border-rule bg-paper-2 p-4">
            <p className="font-medium text-ink">{c}</p>
            <dl className="mt-2 flex flex-col gap-2 text-sm">
              {data.rows.map((row, r) => (
                <div key={r} className="flex flex-col gap-0.5">
                  <dt>{row.label}</dt>
                  <dd className="text-ink">{renderInline(row.values[ci] ?? "")}</dd>
                </div>
              ))}
            </dl>
          </li>
        ))}
      </ul>
    </>
  );
}

export function Timeline({ data }: { data: TimelineData }) {
  return (
    <ol className="flex flex-col">
      {data.events.map((e, i) => (
        <li key={i} className="relative grid grid-cols-[minmax(4.5rem,auto)_1fr] gap-x-4 pb-5 last:pb-0">
          <span className="measured pt-0.5 text-right text-sm text-muted">{e.when}</span>
          <div className="relative flex flex-col gap-1 border-l border-rule-2 pl-5">
            <span
              aria-hidden
              className="absolute top-1.5 -left-[5px] size-[9px] rounded-full border border-accent bg-paper"
            />
            <span className="font-medium text-ink">{e.title}</span>
            {e.detail ? <span className="text-sm text-ink-2">{renderInline(e.detail)}</span> : null}
          </div>
        </li>
      ))}
    </ol>
  );
}

/** Bits grouped into labelled fields; fields wrap as whole units on narrow screens. */
export function Bitfield({ data }: { data: BitfieldData }) {
  const t = useTranslations("learn.blocks");
  return (
    <div className="flex flex-col gap-5">
      {data.rows.map((row, r) => (
        <figure key={r} className="flex flex-col gap-2">
          <figcaption className="measured text-sm text-ink">{row.label}</figcaption>
          <div className="flex flex-wrap gap-x-2 gap-y-3">
            {row.fields.map((f, i) => (
              <div key={i} className="flex flex-col gap-1">
                <div
                  className="flex"
                  role="img"
                  aria-label={t("bitsAlt", { label: f.label, bits: f.bits, value: parseInt(f.bits, 2) })}
                >
                  {f.bits.split("").map((bit, b) => (
                    <span
                      key={b}
                      aria-hidden
                      className={cn(
                        "measured flex h-7 w-[1.15rem] items-center justify-center border-y border-r text-sm first:rounded-l-chip first:border-l last:rounded-r-chip",
                        toneClass[f.tone ?? "plain"],
                        bit === "0" && "opacity-75",
                      )}
                    >
                      {bit}
                    </span>
                  ))}
                </div>
                <span className="text-xs text-muted">
                  {f.label}
                  {data.show_decimal ? <span className="measured text-ink"> = {parseInt(f.bits, 2)}</span> : null}
                </span>
              </div>
            ))}
          </div>
        </figure>
      ))}
    </div>
  );
}

/** A walkthrough: each step's parts and values; values that changed since the last step are marked. */
export function StepThrough({ data }: { data: StepThroughData }) {
  const t = useTranslations("learn.blocks");
  const [current, setCurrent] = useState(0);
  const step = data.steps[Math.min(current, data.steps.length - 1)];
  const previous = current > 0 ? new Map((data.steps[current - 1].state ?? []).map((s) => [s.label, s.value])) : null;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-control border border-rule bg-paper-3 p-5" aria-live="polite">
        <h3 className="text-[length:var(--text-lg)]">{step.title}</h3>
        {step.parts?.length ? (
          <ol className="flex flex-wrap gap-1" aria-label={t("parts")}>
            {step.parts.map((p, i) => (
              <li
                key={i}
                className={cn("measured rounded-chip border px-2.5 py-1.5 text-sm", toneClass[p.tone ?? "plain"])}
              >
                {p.label}
              </li>
            ))}
          </ol>
        ) : null}
        <p className="text-ink-2">{renderInline(step.text)}</p>
        {step.state?.length ? (
          <dl className="grid gap-x-6 gap-y-1.5 text-sm @xl:grid-cols-2">
            {step.state.map((s, i) => {
              const changed = previous !== null && previous.get(s.label) !== s.value;
              return (
                <div key={i} className="flex items-baseline gap-2">
                  <dt>{s.label}</dt>
                  <dd className={cn("measured", changed ? "rounded-chip bg-accent-soft px-1 text-ink" : "text-ink")}>
                    {s.value}
                    {changed ? <span className="sr-only"> ({t("changed")})</span> : null}
                  </dd>
                </div>
              );
            })}
          </dl>
        ) : null}
      </div>
      <Stepper current={current} total={data.steps.length} onChange={setCurrent} />
    </div>
  );
}
