"use client";

import { ShieldAlert } from "lucide-react";
import { useTranslations } from "next-intl";
import { z } from "zod";
import { cn } from "@/lib/utils";
import { useTabs } from "./use-tabs";

export const osiSchema = z.object({
  layers: z
    .array(
      z.object({
        number: z.number().int(),
        name: z.string(),
        unit: z.string(),
        role: z.string(),
        protocols: z.array(z.string()),
        attack: z.string(),
      }),
    )
    .min(1),
});

/** The seven layers as a vertical stack of tabs; the selected layer's details sit beside it. */
export function OsiModel({ data }: { data: z.infer<typeof osiSchema> }) {
  const t = useTranslations("learn.diagrams");
  const tabs = useTabs(data.layers.length, { vertical: true });
  const layer = data.layers[tabs.selected];

  return (
    <div className="grid gap-4 md:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
      <div {...tabs.listProps} aria-label={t("layers")} className="flex flex-col gap-1">
        {data.layers.map((l, i) => {
          const active = i === tabs.selected;
          return (
            <button
              key={l.number}
              type="button"
              {...tabs.tabProps(i)}
              className={cn(
                "flex items-center gap-3 rounded-control border px-3 py-2 text-left text-sm",
                "transition-[background-color,border-color,color] duration-[var(--dur-micro)] ease-out",
                active ? "border-accent bg-accent-soft text-ink" : "border-rule bg-paper-2 text-muted hover:border-rule-2 hover:text-ink",
              )}
            >
              <span className="measured w-5 shrink-0 text-ink">{l.number}</span>
              <span className="font-medium">{l.name}</span>
              <span className="measured ml-auto text-xs">{l.unit}</span>
            </button>
          );
        })}
      </div>
      <div {...tabs.panelProps} className="flex flex-col gap-4 rounded-control border border-rule bg-paper-3 p-5">
        <div className="flex items-baseline gap-3">
          <span className="measured text-[length:var(--text-2xl)] leading-none text-accent">{layer.number}</span>
          <h4 className="text-[length:var(--text-lg)]">{layer.name}</h4>
        </div>
        <p className="text-ink-2">{layer.role}</p>
        <dl className="grid gap-3 text-sm">
          <div className="flex flex-col gap-1">
            <dt>{t("unit")}</dt>
            <dd className="measured text-ink">{layer.unit}</dd>
          </div>
          <div className="flex flex-col gap-1.5">
            <dt>{t("protocols")}</dt>
            <dd>
              <ul className="flex flex-wrap gap-1.5">
                {layer.protocols.map((p) => (
                  <li key={p} className="measured rounded-chip border border-rule bg-paper-2 px-1.5 py-0.5 text-ink">
                    {p}
                  </li>
                ))}
              </ul>
            </dd>
          </div>
          <div className="flex gap-2.5 rounded-control border border-attn bg-attn-soft p-3">
            <ShieldAlert aria-hidden className="mt-0.5 size-4 shrink-0 text-attn" />
            <div className="flex flex-col gap-0.5">
              <dt className="font-medium text-ink">{t("attack")}</dt>
              <dd className="text-ink">{layer.attack}</dd>
            </div>
          </div>
        </dl>
      </div>
    </div>
  );
}
