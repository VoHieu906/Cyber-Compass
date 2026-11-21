"use client";

import { AlertTriangle, Info, Lightbulb } from "lucide-react";
import { useTranslations } from "next-intl";
import { cn } from "@/lib/utils";
import { useTabs } from "../diagrams/use-tabs";
import type { LayerStack as LayerStackData } from "./schemas";

const noteStyle = {
  info: { box: "border-rule bg-paper-2", icon: Info, iconClass: "text-muted" },
  tip: { box: "border-accent bg-accent-soft", icon: Lightbulb, iconClass: "text-accent" },
  warning: { box: "border-attn bg-attn-soft", icon: AlertTriangle, iconClass: "text-attn" },
} as const;

/** Layers as a vertical stack of tabs, top of the stack first; the selected layer's details beside it. */
export function LayerStack({ data }: { data: LayerStackData }) {
  const t = useTranslations("learn.blocks");
  const tabs = useTabs(data.layers.length, { vertical: true });
  const layer = data.layers[Math.min(tabs.selected, data.layers.length - 1)];
  const note = layer.note ? noteStyle[layer.note.tone] : null;

  return (
    <div className="grid gap-4 @xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
      <div {...tabs.listProps} aria-label={t("layers")} className="flex flex-col gap-1">
        {data.layers.map((l, i) => {
          const active = i === tabs.selected;
          return (
            <button
              key={i}
              type="button"
              {...tabs.tabProps(i)}
              className={cn(
                "flex items-center gap-3 rounded-control border px-3 py-2 text-left text-sm",
                "transition-[background-color,border-color,color] duration-[var(--dur-micro)] ease-out",
                active
                  ? "border-accent bg-accent-soft text-ink"
                  : "border-rule bg-paper-2 text-muted hover:border-rule-2 hover:text-ink",
              )}
            >
              {l.tag ? <span className="measured w-5 shrink-0 text-ink">{l.tag}</span> : null}
              <span className="font-medium">{l.name}</span>
              {l.meta ? <span className="measured ml-auto text-xs">{l.meta}</span> : null}
            </button>
          );
        })}
      </div>
      <div {...tabs.panelProps} className="flex flex-col gap-4 rounded-control border border-rule bg-paper-3 p-5">
        <div className="flex items-baseline gap-3">
          {layer.tag ? (
            <span className="measured text-[length:var(--text-2xl)] leading-none text-accent">{layer.tag}</span>
          ) : null}
          <h3 className="text-[length:var(--text-lg)]">{layer.name}</h3>
        </div>
        <p className="text-ink-2">{layer.description}</p>
        {layer.facts?.length || layer.tags?.length ? (
          <dl className="grid gap-3 text-sm">
            {layer.facts?.map((f, i) => (
              <div key={i} className="flex flex-col gap-1">
                <dt>{f.label}</dt>
                <dd className="text-ink">{f.value}</dd>
              </div>
            ))}
            {layer.tags?.length ? (
              <div className="flex flex-col gap-1.5">
                <dt>{data.tags_label ?? t("examples")}</dt>
                <dd>
                  <ul className="flex flex-wrap gap-1.5">
                    {layer.tags.map((tag, i) => (
                      <li
                        key={i}
                        className="measured rounded-chip border border-rule bg-paper-2 px-1.5 py-0.5 text-ink"
                      >
                        {tag}
                      </li>
                    ))}
                  </ul>
                </dd>
              </div>
            ) : null}
          </dl>
        ) : null}
        {layer.note && note ? (
          <div className={cn("flex gap-2.5 rounded-control border p-3 text-sm", note.box)}>
            <note.icon aria-hidden className={cn("mt-0.5 size-4 shrink-0", note.iconClass)} />
            <p className="flex flex-col gap-0.5 text-ink">
              {layer.note.label ? <span className="font-medium">{layer.note.label}</span> : null}
              <span>{layer.note.text}</span>
            </p>
          </div>
        ) : null}
      </div>
    </div>
  );
}
