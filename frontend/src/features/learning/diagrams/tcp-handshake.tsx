"use client";

import { ChevronLeft, ChevronRight, RotateCcw } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { z } from "zod";
import { Button } from "@/components/ui/button";

export const handshakeSchema = z.object({
  participants: z.object({ client: z.string(), server: z.string() }),
  steps: z
    .array(
      z.object({
        from: z.enum(["client", "server"]),
        label: z.string(),
        fields: z.string(),
        explanation: z.string(),
        states: z.object({ client: z.string(), server: z.string() }),
      }),
    )
    .min(1),
});

const LEFT = 70;
const RIGHT = 410;
const TOP = 48;
const GAP = 64;

/** A sequence diagram that draws one message per step; the newest arrow is the accent. */
export function TcpHandshake({ data }: { data: z.infer<typeof handshakeSchema> }) {
  const t = useTranslations("learn.diagrams");
  const [current, setCurrent] = useState(0);
  const step = data.steps[current];
  const height = TOP + data.steps.length * GAP + 24;

  return (
    <div className="flex flex-col gap-4">
      <div className="overflow-x-auto rounded-control border border-rule bg-paper-3">
        <svg
          viewBox={`0 0 480 ${height}`}
          role="img"
          aria-label={data.steps
            .slice(0, current + 1)
            .map((s) => `${data.participants[s.from]}: ${s.label} ${s.fields}`)
            .join(", ")}
          className="block w-full min-w-[22rem]"
        >
          {(["client", "server"] as const).map((who) => {
            const x = who === "client" ? LEFT : RIGHT;
            return (
              <g key={who}>
                <text x={x} y={22} textAnchor="middle" fill="var(--color-ink)" fontSize="14" fontWeight="500" fontFamily="var(--font-display)">
                  {data.participants[who]}
                </text>
                <line x1={x} y1={32} x2={x} y2={height - 8} stroke="var(--color-rule-2)" strokeWidth="1.5" strokeDasharray="3 4" />
              </g>
            );
          })}
          {data.steps.map((s, i) => {
            const y = TOP + i * GAP + 24;
            const fromX = s.from === "client" ? LEFT : RIGHT;
            const toX = s.from === "client" ? RIGHT : LEFT;
            const dir = toX > fromX ? -1 : 1;
            const visible = i <= current;
            const active = i === current;
            const colour = active ? "var(--color-accent)" : "var(--color-muted)";
            return (
              <g key={i} style={{ opacity: visible ? 1 : 0, transition: "opacity var(--dur-short) var(--ease-out)" }} aria-hidden>
                <line x1={fromX} y1={y} x2={toX + dir * 2} y2={y} stroke={colour} strokeWidth={active ? 2 : 1.5} />
                <path d={`M ${toX} ${y} l ${dir * 9} -5 v 10 z`} fill={colour} />
                <text x={(LEFT + RIGHT) / 2} y={y - 10} textAnchor="middle" fill="var(--color-ink)" fontSize="13" fontFamily="var(--font-measured)">
                  {s.label}
                  <tspan fill="var(--color-muted)"> {s.fields}</tspan>
                </text>
              </g>
            );
          })}
        </svg>
      </div>

      <div className="flex flex-col gap-3 rounded-control border border-rule bg-paper-2 p-4" aria-live="polite">
        <p className="measured text-sm text-muted">{t("step", { current: current + 1, total: data.steps.length })}</p>
        <p className="text-ink">
          <span className="measured mr-2 text-accent">{step.label}</span>
          {step.explanation}
        </p>
        <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
          {(["client", "server"] as const).map((who) => (
            <div key={who} className="flex gap-2">
              <dt>
                {data.participants[who]} {t("state").toLowerCase()}
              </dt>
              <dd className="measured text-ink">{step.states[who]}</dd>
            </div>
          ))}
        </dl>
      </div>

      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" size="sm" disabled={current === 0} onClick={() => setCurrent((c) => c - 1)}>
          <ChevronLeft aria-hidden />
          {t("previousStep")}
        </Button>
        {current < data.steps.length - 1 ? (
          <Button size="sm" onClick={() => setCurrent((c) => c + 1)}>
            {t("nextStep")}
            <ChevronRight aria-hidden />
          </Button>
        ) : (
          <Button variant="secondary" size="sm" onClick={() => setCurrent(0)}>
            <RotateCcw aria-hidden />
            {t("restart")}
          </Button>
        )}
      </div>
    </div>
  );
}
