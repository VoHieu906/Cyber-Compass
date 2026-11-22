"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import type { Sequence as SequenceData } from "./schemas";
import { Stepper } from "./stepper";

const LANE = 150;
const MARGIN = 75;
const TOP = 48;
const GAP = 60;

/**
 * A sequence diagram drawn one message per step; earlier messages stay, the newest is the accent.
 * Two actors render like the original TCP handshake diagram; up to five share the width.
 */
export function Sequence({ data }: { data: SequenceData }) {
  const t = useTranslations("learn.blocks");
  const [current, setCurrent] = useState(0);
  const step = data.messages[Math.min(current, data.messages.length - 1)];
  const width = Math.max(data.actors.length === 2 ? 380 : 480, MARGIN * 2 + (data.actors.length - 1) * LANE);
  const spacing = (width - MARGIN * 2) / (data.actors.length - 1);
  const xOf = new Map(data.actors.map((a, i) => [a.id, MARGIN + i * spacing]));
  const nameOf = new Map(data.actors.map((a) => [a.id, a.name]));
  const height = TOP + data.messages.length * GAP + 24;
  const states = Object.entries(step.states ?? {});

  return (
    <div className="flex flex-col gap-4">
      {/* Focusable so a keyboard can scroll a five-actor sequence sideways on a phone (WCAG 2.1.1), like code blocks. */}
      <div tabIndex={0} className="overflow-x-auto rounded-control border border-rule bg-paper-3">
        <svg
          viewBox={`0 0 ${width} ${height}`}
          role="img"
          aria-label={data.messages
            .slice(0, current + 1)
            .map((m) =>
              t("messageAlt", {
                from: nameOf.get(m.from)!,
                to: nameOf.get(m.to)!,
                label: `${m.label}${m.detail ? ` ${m.detail}` : ""}`,
              }),
            )
            .join(". ")}
          className="mx-auto block w-full"
          style={{ minWidth: Math.round(width * 0.85), maxWidth: Math.round(width * 1.5) }}
        >
          {data.actors.map((a) => {
            const x = xOf.get(a.id)!;
            return (
              <g key={a.id}>
                <text
                  x={x}
                  y={22}
                  textAnchor="middle"
                  fontSize="14"
                  fontWeight="500"
                  style={{ fill: "var(--color-ink)", fontFamily: "var(--font-display)" }}
                >
                  {a.name}
                </text>
                <line
                  x1={x}
                  y1={32}
                  x2={x}
                  y2={height - 8}
                  strokeWidth="1.5"
                  strokeDasharray="3 4"
                  style={{ stroke: "var(--color-rule-2)" }}
                />
              </g>
            );
          })}
          {data.messages.map((m, i) => {
            const y = TOP + i * GAP + 24;
            const fromX = xOf.get(m.from)!;
            const toX = xOf.get(m.to)!;
            const dir = toX > fromX ? -1 : 1;
            const active = i === current;
            const colour = active ? "var(--color-accent)" : "var(--color-muted)";
            return (
              <g
                key={i}
                style={{ opacity: i <= current ? 1 : 0, transition: "opacity var(--dur-short) var(--ease-out)" }}
                aria-hidden
              >
                <line
                  x1={fromX}
                  y1={y}
                  x2={toX + dir * 2}
                  y2={y}
                  strokeWidth={active ? 2 : 1.5}
                  style={{ stroke: colour }}
                />
                <path d={`M ${toX} ${y} l ${dir * 9} -5 v 10 z`} style={{ fill: colour }} />
                <text
                  x={(fromX + toX) / 2}
                  y={y - 10}
                  textAnchor="middle"
                  fontSize="13"
                  style={{ fill: "var(--color-ink)", fontFamily: "var(--font-measured)" }}
                >
                  {m.label}
                  {m.detail ? <tspan style={{ fill: "var(--color-muted)" }}> {m.detail}</tspan> : null}
                </text>
              </g>
            );
          })}
        </svg>
      </div>

      <div className="flex flex-col gap-3 rounded-control border border-rule bg-paper-2 p-4" aria-live="polite">
        <p className="text-ink">
          <span className="measured mr-2 text-accent">{step.label}</span>
          {step.explanation}
        </p>
        {states.length ? (
          <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
            {states.map(([actor, state]) => (
              <div key={actor} className="flex gap-2">
                <dt>{t("stateOf", { actor: nameOf.get(actor) ?? actor })}</dt>
                <dd className="measured text-ink">{state}</dd>
              </div>
            ))}
          </dl>
        ) : null}
      </div>

      <Stepper current={current} total={data.messages.length} onChange={setCurrent} />
    </div>
  );
}
