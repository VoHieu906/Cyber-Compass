"use client";

import { Plus, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import type { CheckpointFormProps } from "../engine-forms";
import { Field } from "../parts";

type Packet = { proto: "tcp" | "udp"; port: number; source: string; expect: "allow" | "deny" };

const MAX_PACKETS = 20;

/** The test packets of a traffic checkpoint, each with the verdict the lab expects once the rules are right. */
export function PacketsForm({ checkpoint, path, errors, onChange }: CheckpointFormProps) {
  const t = useTranslations("labAdmin.forms.packets");
  const packets = ((checkpoint.packets as Packet[] | undefined) ?? []) as Packet[];
  const set = (next: Packet[]) => onChange({ ...checkpoint, packets: next });
  const setPacket = (i: number, next: Packet) => set(packets.map((p, j) => (j === i ? next : p)));
  const at = (i: number, field: string) => errors[`${path}.packets.${i}.${field}`];

  return (
    <fieldset className="flex flex-col gap-3">
      <legend className="text-sm font-medium text-ink">{t("legend")}</legend>
      <p className="text-xs text-muted">{t("hint")}</p>
      <ol className="flex flex-col gap-3">
        {packets.map((p, i) => (
          <li
            key={i}
            className="grid gap-3 rounded-card border border-rule bg-paper-2 p-3 sm:grid-cols-[repeat(4,minmax(0,1fr))_auto] sm:items-end"
          >
            <Field label={t("proto", { number: i + 1 })} messages={at(i, "proto")}>
              {({ id, invalid }) => (
                <select
                  id={id}
                  value={p.proto}
                  onChange={(e) => setPacket(i, { ...p, proto: e.target.value as Packet["proto"] })}
                  className={controlClasses(invalid)}
                >
                  <option value="tcp">tcp</option>
                  <option value="udp">udp</option>
                </select>
              )}
            </Field>
            <Field label={t("port", { number: i + 1 })} messages={at(i, "port")}>
              {({ id, invalid }) => (
                <input
                  id={id}
                  inputMode="numeric"
                  value={Number.isFinite(p.port) ? p.port : ""}
                  onChange={(e) => setPacket(i, { ...p, port: Number(e.target.value.replace(/\D/g, "")) })}
                  className={controlClasses(invalid, "font-mono text-sm")}
                />
              )}
            </Field>
            <Field label={t("source", { number: i + 1 })} messages={at(i, "source")}>
              {({ id, invalid, describedBy }) => (
                <input
                  id={id}
                  value={p.source}
                  spellCheck={false}
                  aria-invalid={invalid || undefined}
                  aria-describedby={describedBy}
                  onChange={(e) => setPacket(i, { ...p, source: e.target.value })}
                  className={controlClasses(invalid, "font-mono text-sm")}
                />
              )}
            </Field>
            <Field label={t("expect", { number: i + 1 })} messages={at(i, "expect")}>
              {({ id, invalid }) => (
                <select
                  id={id}
                  value={p.expect}
                  onChange={(e) => setPacket(i, { ...p, expect: e.target.value as Packet["expect"] })}
                  className={controlClasses(invalid)}
                >
                  <option value="allow">{t("allow")}</option>
                  <option value="deny">{t("deny")}</option>
                </select>
              )}
            </Field>
            <Button
              variant="ghost"
              size="sm"

              disabled={packets.length === 1}
              onClick={() => set(packets.filter((_, j) => j !== i))}
              aria-label={t("remove", { number: i + 1 })}
            >
              <Trash2 aria-hidden />
            </Button>
          </li>
        ))}
      </ol>
      <Button
        variant="secondary"
        size="sm"
        className="self-start"
        disabled={packets.length >= MAX_PACKETS}
        onClick={() => set([...packets, { proto: "tcp", port: 80, source: "203.0.113.1", expect: "allow" }])}
      >
        <Plus aria-hidden />
        {t("add")}
      </Button>
    </fieldset>
  );
}
