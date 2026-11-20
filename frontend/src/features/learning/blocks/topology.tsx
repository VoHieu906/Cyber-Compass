"use client";

import {
  BrickWallShield,
  Cloud,
  Database,
  Globe,
  Laptop,
  Monitor,
  Network,
  Router,
  Server,
  Skull,
  Smartphone,
  Wifi,
  type LucideIcon,
} from "lucide-react";
import { useTranslations } from "next-intl";
import { useState, type KeyboardEvent } from "react";
import { wrapLabel } from "@/lib/graph/layout";
import type { DEVICE_KINDS, Topology as TopologyData } from "./schemas";

type Kind = (typeof DEVICE_KINDS)[number];

export const DEVICE_ICONS: Record<Kind, LucideIcon> = {
  router: Router,
  switch: Network,
  firewall: BrickWallShield,
  server: Server,
  database: Database,
  workstation: Monitor,
  laptop: Laptop,
  phone: Smartphone,
  "access-point": Wifi,
  cloud: Cloud,
  internet: Globe,
  attacker: Skull,
};

const ZONE_TONE = {
  trusted: "var(--color-ok)",
  untrusted: "var(--color-bad)",
  dmz: "var(--color-attn)",
  neutral: "var(--color-rule-2)",
} as const;

const WIDTH = 1000;
const HEIGHT = { "16:9": 562, "4:3": 750, "2:1": 500 } as const;
const ICON = 48;
// Devices may sit on the 0 and 100 edges; the drawing area is inset so they stay whole.
const INSET = { x: 80, top: 44, bottom: 84 };

/** Devices and links at the author's positions (percent of the canvas), with optional zones. */
export function Topology({ data }: { data: TopologyData }) {
  const t = useTranslations("learn.blocks");
  const [selected, setSelected] = useState<string | null>(null);
  const height = HEIGHT[data.aspect ?? "16:9"];
  const px = (x: number) => INSET.x + (x / 100) * (WIDTH - INSET.x * 2);
  const py = (y: number) => INSET.top + (y / 100) * (height - INSET.top - INSET.bottom);
  const byId = new Map(data.devices.map((d) => [d.id, d]));
  const current = selected ? byId.get(selected) : undefined;
  const hasDetails = data.devices.some((d) => d.detail);

  const keyDown = (id: string) => (e: KeyboardEvent<SVGGElement>) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      setSelected((s) => (s === id ? null : id));
    }
  };

  return (
    <div className="flex flex-col gap-3">
      <div className="overflow-x-auto rounded-control border border-rule bg-paper-3">
        <svg
          viewBox={`0 0 ${WIDTH} ${height}`}
          role="group"
          aria-label={t("topologyLabel", { count: data.devices.length })}
          className="block w-full min-w-[40rem]"
        >
          {(data.zones ?? []).map((z) => {
            const colour = ZONE_TONE[z.tone ?? "neutral"];
            const x = px(z.x) - INSET.x / 2;
            const y = py(z.y) - INSET.top / 2 - 6;
            const w = (z.w / 100) * (WIDTH - INSET.x * 2) + INSET.x;
            const h = (z.h / 100) * (height - INSET.top - INSET.bottom) + INSET.top / 2 + INSET.bottom - 4;
            return (
              <g key={z.id} aria-hidden>
                <rect
                  x={Math.max(4, x)}
                  y={Math.max(4, y)}
                  width={Math.min(w, WIDTH - 8 - Math.max(4, x))}
                  height={Math.min(h, height - 8 - Math.max(4, y))}
                  rx={10}
                  strokeWidth={1.5}
                  strokeDasharray="6 5"
                  style={{ stroke: colour, fill: `color-mix(in oklab, ${colour} 7%, transparent)` }}
                />
                <text
                  x={Math.max(4, x) + 12}
                  y={Math.max(4, y) + 26}
                  fontSize={18}
                  fontWeight={500}
                  style={{ fill: colour, fontFamily: "var(--font-display)" }}
                >
                  {z.label}
                </text>
              </g>
            );
          })}

          {data.links.map((l, i) => {
            const a = byId.get(l.from)!;
            const b = byId.get(l.to)!;
            const [x1, y1, x2, y2] = [px(a.x), py(a.y), px(b.x), py(b.y)];
            const style = l.style ?? "wired";
            return (
              <g key={i} aria-hidden>
                <line
                  x1={x1}
                  y1={y1}
                  x2={x2}
                  y2={y2}
                  strokeWidth={style === "vpn" ? 2.5 : 1.75}
                  strokeDasharray={style === "wireless" ? "2 6" : style === "vpn" ? "10 5" : undefined}
                  strokeLinecap="round"
                  style={{ stroke: style === "vpn" ? "var(--color-accent)" : "var(--color-muted)" }}
                />
                {l.label ? (
                  <text
                    x={(x1 + x2) / 2}
                    y={(y1 + y2) / 2 - 8}
                    textAnchor="middle"
                    fontSize={17}
                    style={{
                      fill: "var(--color-muted)",
                      fontFamily: "var(--font-measured)",
                      paintOrder: "stroke",
                      stroke: "var(--color-paper-3)",
                      strokeWidth: 5,
                    }}
                  >
                    {l.label}
                  </text>
                ) : null}
              </g>
            );
          })}

          {data.devices.map((d) => {
            const Icon = DEVICE_ICONS[d.kind];
            const x = px(d.x);
            const y = py(d.y);
            const active = selected === d.id;
            const lines = wrapLabel(d.label, 14, 2);
            const interactive = Boolean(d.detail);
            return (
              <g
                key={d.id}
                {...(interactive
                  ? {
                      role: "button",
                      tabIndex: 0,
                      "aria-pressed": active,
                      "aria-label": d.address ? `${d.label}, ${d.address}` : d.label,
                      onClick: () => setSelected((s) => (s === d.id ? null : d.id)),
                      onKeyDown: keyDown(d.id),
                      className:
                        "cursor-pointer outline-none [&:focus-visible>.device-bg]:[stroke:var(--color-accent)] [&:focus-visible>.device-bg]:[stroke-width:3px]",
                    }
                  : { role: "img", "aria-label": d.address ? `${d.label}, ${d.address}` : d.label })}
              >
                <circle
                  className="device-bg"
                  cx={x}
                  cy={y}
                  r={ICON / 2 + 10}
                  strokeWidth={active ? 2.5 : 1.5}
                  style={{
                    fill: active ? "var(--color-accent-soft)" : "var(--color-paper)",
                    stroke: active
                      ? "var(--color-accent)"
                      : d.kind === "attacker"
                        ? "var(--color-bad)"
                        : "var(--color-rule-2)",
                  }}
                />
                <Icon
                  x={x - ICON / 2 + 4}
                  y={y - ICON / 2 + 4}
                  width={ICON - 8}
                  height={ICON - 8}
                  strokeWidth={1.75}
                  aria-hidden
                  style={{ color: d.kind === "attacker" ? "var(--color-bad)" : "var(--color-ink)" }}
                />
                <text textAnchor="middle" fontSize={20} aria-hidden style={{ fill: "var(--color-ink)" }}>
                  {lines.map((line, i) => (
                    <tspan key={i} x={x} y={y + ICON / 2 + 32 + i * 22}>
                      {line}
                    </tspan>
                  ))}
                  {d.address ? (
                    <tspan
                      x={x}
                      y={y + ICON / 2 + 32 + lines.length * 22}
                      fontSize={16}
                      style={{ fill: "var(--color-muted)", fontFamily: "var(--font-measured)" }}
                    >
                      {d.address}
                    </tspan>
                  ) : null}
                </text>
                {interactive && !active ? (
                  <circle
                    cx={x + ICON / 2 + 6}
                    cy={y - ICON / 2 - 4}
                    r={4}
                    aria-hidden
                    style={{ fill: "var(--color-accent)" }}
                  />
                ) : null}
              </g>
            );
          })}
        </svg>
      </div>

      {hasDetails ? (
        <div className="min-h-14 rounded-control border border-rule bg-paper-2 p-4 text-sm" aria-live="polite">
          {current?.detail ? (
            <p className="text-ink">
              <span className="mr-2 font-medium">{current.label}</span>
              {current.detail}
            </p>
          ) : (
            <p>{t("selectDevice")}</p>
          )}
        </div>
      ) : null}

      <details className="text-sm">
        <summary className="cursor-pointer rounded-control text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
          {t("textVersion")}
        </summary>
        <div className="mt-2 flex flex-col gap-3 rounded-control border border-rule bg-paper-2 p-4">
          {data.zones?.length ? (
            <ul className="flex flex-col gap-1">
              {data.zones.map((z) => {
                const inside = data.devices.filter(
                  (d) => d.x >= z.x && d.x <= z.x + z.w && d.y >= z.y && d.y <= z.y + z.h,
                );
                return (
                  <li key={z.id}>
                    {t("zoneOutline", { zone: z.label, devices: inside.map((d) => d.label).join(", ") || "–" })}
                  </li>
                );
              })}
            </ul>
          ) : null}
          <ul className="flex list-disc flex-col gap-1 pl-5">
            {data.devices.map((d) => {
              const peers = data.links
                .filter((l) => l.from === d.id || l.to === d.id)
                .map((l) => byId.get(l.from === d.id ? l.to : l.from)!.label + (l.label ? ` (${l.label})` : ""));
              return (
                <li key={d.id}>
                  <span className="text-ink">{d.label}</span>
                  {d.address ? <span className="measured"> {d.address}</span> : null}
                  {peers.length ? <span>. {t("connectsTo", { peers: peers.join(", ") })}</span> : null}
                  {d.detail ? <span className="block">{d.detail}</span> : null}
                </li>
              );
            })}
          </ul>
        </div>
      </details>
    </div>
  );
}
