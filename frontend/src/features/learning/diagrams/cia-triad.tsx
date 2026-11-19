"use client";

import { useTranslations } from "next-intl";
import { z } from "zod";
import { cn } from "@/lib/utils";
import { useTabs } from "./use-tabs";

export const ciaSchema = z.object({
  properties: z
    .array(
      z.object({
        key: z.string(),
        name: z.string(),
        definition: z.string(),
        example: z.string(),
        controls: z.array(z.string()),
      }),
    )
    .length(3),
});

// Triangle vertices: top, bottom right, bottom left.
const VERTICES = [
  { x: 150, y: 30 },
  { x: 264, y: 222 },
  { x: 36, y: 222 },
];

/** The triad as a triangle (a picture of which property is selected) plus tabs carrying the content. */
export function CiaTriad({ data }: { data: z.infer<typeof ciaSchema> }) {
  const t = useTranslations("learn.diagrams");
  const tabs = useTabs(3);
  const property = data.properties[tabs.selected];

  return (
    <div className="grid items-start gap-5 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
      <svg viewBox="0 0 300 250" aria-hidden className="mx-auto w-full max-w-[18rem]">
        <polygon
          points={VERTICES.map((v) => `${v.x},${v.y}`).join(" ")}
          fill="var(--color-paper-3)"
          stroke="var(--color-rule-2)"
          strokeWidth="1.5"
        />
        {VERTICES.map((v, i) => {
          const active = i === tabs.selected;
          return (
            <g key={i}>
              <circle
                cx={v.x}
                cy={v.y}
                r={active ? 15 : 11}
                fill={active ? "var(--color-accent)" : "var(--color-paper-2)"}
                stroke={active ? "var(--color-accent)" : "var(--color-rule-2)"}
                strokeWidth="1.5"
              />
              <text
                x={v.x}
                y={v.y + 4.5}
                textAnchor="middle"
                fontSize="12"
                fontFamily="var(--font-measured)"
                fill={active ? "var(--color-accent-ink)" : "var(--color-ink)"}
              >
                {data.properties[i].name[0]}
              </text>
            </g>
          );
        })}
      </svg>

      <div className="flex flex-col gap-4">
        <div {...tabs.listProps} aria-label={data.properties.map((p) => p.name).join(", ")} className="flex flex-wrap gap-1">
          {data.properties.map((p, i) => (
            <button
              key={p.key}
              type="button"
              {...tabs.tabProps(i)}
              className={cn(
                "rounded-control border px-3 py-1.5 text-sm font-medium transition-[background-color,border-color,color] duration-[var(--dur-micro)] ease-out",
                i === tabs.selected ? "border-accent bg-accent-soft text-ink" : "border-rule text-muted hover:border-rule-2 hover:text-ink",
              )}
            >
              {p.name}
            </button>
          ))}
        </div>
        <div {...tabs.panelProps} className="flex flex-col gap-3 rounded-control border border-rule bg-paper-3 p-5">
          <p className="text-ink">{property.definition}</p>
          <div className="flex flex-col gap-1 text-sm">
            <p className="font-medium text-ink">{t("example")}</p>
            <p>{property.example}</p>
          </div>
          <div className="flex flex-col gap-1.5 text-sm">
            <p className="font-medium text-ink">{t("controls")}</p>
            <ul className="flex flex-wrap gap-1.5">
              {property.controls.map((c) => (
                <li key={c} className="rounded-chip border border-rule bg-paper-2 px-1.5 py-0.5 text-ink">
                  {c}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
